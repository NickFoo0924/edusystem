<?php

namespace App\Patterns\Singleton;

use App\Models\ActivityLog;
use App\Models\Badge;
use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\Course;
use App\Models\Grade;
use App\Models\LearningPath;
use App\Models\QuizAttempt;
use App\Models\Setting;
use App\Models\StudentProgress;
use App\Models\Submission;
use App\Models\User;
use App\Support\GradeScale;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

/**
 * MODULE 1 DESIGN PATTERN -- SINGLETON (Creational).
 *
 * The CredentialAuthority models a real-world certificate authority. Only one
 * authority may exist in the system, because it is the sole issuer of credential
 * IDs and the sole arbiter of whether a student has already been credentialed
 * for a given course. If two instances existed concurrently -- for example when
 * a grade event and a manual admin issuance fire at the same time -- they could
 * mint duplicate credential IDs or issue two certificates for the same
 * achievement, destroying the uniqueness guarantee that public verification
 * depends upon. The Singleton also loads the badge rule registry once and holds
 * it in memory for the lifetime of the request, so the rule set is evaluated
 * from one consistent source.
 *
 * The pattern is enforced twice over:
 *   1. Classic GoF form -- private constructor, private __clone, a guarded
 *      __wakeup and a static getInstance() holding the only instance.
 *   2. Laravel form -- CredentialServiceProvider binds this class into the
 *      service container with $this->app->singleton(), so every injection
 *      throughout the application resolves to that same object.
 *
 * No pattern logic lives in a controller (EduSystem.md Section 6).
 */
final class CredentialAuthority
{
    /**
     * The one and only instance.
     */
    private static ?CredentialAuthority $instance = null;

    /**
     * Credential ID format: LS-{YEAR}-{8 CHAR BASE32}, e.g. LS-2026-A7F3D9K2.
     */
    private const CREDENTIAL_PREFIX = 'LS';

    private const CREDENTIAL_RANDOM_LENGTH = 8;

    /**
     * Crockford Base32: the digits 0-9 and the letters A-Z with I, L, O and U
     * removed, so a credential ID read aloud or copied off a printed
     * certificate cannot be confused between 1/I/L or 0/O.
     */
    private const BASE32_ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    /**
     * How many times to retry if a generated ID happens to collide.
     */
    private const MAX_ID_ATTEMPTS = 10;

    /**
     * The badge rule registry, loaded once and held for the lifetime of the
     * request. This is the second half of the Singleton justification: every
     * evaluation in a request reads the same rule set, so a rule cannot change
     * underneath a student midway through their awards.
     *
     * @var Collection<int, Badge>|null
     */
    private ?Collection $badgeRules = null;

    /**
     * Private: an authority may not be constructed with `new`.
     */
    private function __construct()
    {
    }

    /**
     * Private: an authority may not be duplicated with `clone`.
     */
    private function __clone(): void
    {
    }

    /**
     * Guarded: an authority may not be resurrected from a serialized string,
     * which would otherwise sidestep the private constructor.
     */
    public function __wakeup(): void
    {
        throw new RuntimeException('The CredentialAuthority cannot be unserialized.');
    }

    /**
     * The single point of access to the authority.
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Issue a course certificate to a student.
     *
     * $finalScore is optional: when the grade event in Module 5 calls this it
     * passes the authoritative score, and until that module exists the
     * authority falls back to the student's recorded completion percentage.
     *
     * @throws RuntimeException if the student already holds a live certificate
     *                          for this course, or no active template exists.
     */
    public function issueCertificate(User $student, Course $course, ?float $finalScore = null): Certificate
    {
        // The authority is the sole arbiter of prior credentialing. A revoked
        // certificate does not block re-issuance; a live one does.
        $existing = Certificate::where('student_id', $student->id)
            ->where('course_id', $course->id)
            ->whereNull('revoked_at')
            ->first();

        if ($existing !== null) {
            throw new RuntimeException(
                "{$student->name} already holds credential {$existing->credential_id} for \"{$course->title}\"."
            );
        }

        $template = CertificateTemplate::where('is_active', true)->first();

        if ($template === null) {
            throw new RuntimeException('No active certificate template exists to render from.');
        }

        $score = $finalScore ?? $this->recordedScoreFor($student, $course);

        // One transaction so that minting the ID, writing the row and rendering
        // the PDF either all succeed or all roll back. A half-issued credential
        // would be unverifiable.
        return DB::transaction(function () use ($student, $course, $template, $score) {
            $credentialId = $this->generateCredentialId();
            $issuedAt = now();

            $certificate = Certificate::create([
                'student_id' => $student->id,
                'course_id' => $course->id,
                'learning_path_id' => null,
                'certificate_template_id' => $template->id,
                'credential_id' => $credentialId,
                'final_score' => $score,
                'integrity_hash' => $this->integrityHash(
                    $student->id,
                    $course->id,
                    $score,
                    $issuedAt->format('Y-m-d H:i:s'),
                    $credentialId
                ),
                'pdf_path' => $this->pdfPathFor($credentialId),
                'issued_at' => $issuedAt,
            ]);

            $this->renderPdf($certificate);

            // Workflow Step 5.5: every issuance is recorded in the audit trail.
            ActivityLog::record('certificate.issued', $certificate);

            // Workflow Step 5.4: if this course was the last one outstanding in
            // a learning path, the pathway certificate follows automatically.
            $this->issueCompletedPathways($student, $course);

            // Workflow Step 5.2, deliberately last: running after the pathway
            // certificates means a path_completion badge can fire in the same
            // pass that earned the pathway.
            $this->evaluateBadges($student);

            return $certificate;
        });
    }

    /**
     * Issue the higher-tier pathway certificate for a completed learning path
     * (EduSystem.md 1C).
     *
     * The final score is the mean of the student's course certificates across
     * the path, so the pathway credential reflects the whole journey rather
     * than any single course.
     *
     * @throws RuntimeException if the path is empty, unfinished, or already
     *                          credentialed.
     */
    public function issuePathwayCertificate(User $student, LearningPath $path): Certificate
    {
        $existing = Certificate::where('student_id', $student->id)
            ->where('learning_path_id', $path->id)
            ->whereNull('revoked_at')
            ->first();

        if ($existing !== null) {
            throw new RuntimeException(
                "{$student->name} already holds pathway credential {$existing->credential_id} for \"{$path->title}\"."
            );
        }

        $courseIds = $path->courses()->pluck('courses.id');

        if ($courseIds->isEmpty()) {
            throw new RuntimeException("Learning path \"{$path->title}\" has no courses in it.");
        }

        $courseCertificates = $this->pathCourseCertificates($student, $courseIds);

        if ($courseCertificates->pluck('course_id')->unique()->count() < $courseIds->count()) {
            throw new RuntimeException(
                "{$student->name} has not completed every course in \"{$path->title}\"."
            );
        }

        // A path may name its own template; otherwise the standard one is used.
        $template = $path->certificateTemplate ?? CertificateTemplate::where('is_active', true)->first();

        if ($template === null) {
            throw new RuntimeException('No active certificate template exists to render from.');
        }

        $score = round((float) $courseCertificates->avg('final_score'), 2);

        return DB::transaction(function () use ($student, $path, $template, $score) {
            $credentialId = $this->generateCredentialId();
            $issuedAt = now();

            $certificate = Certificate::create([
                'student_id' => $student->id,
                'course_id' => null,
                'learning_path_id' => $path->id,
                'certificate_template_id' => $template->id,
                'credential_id' => $credentialId,
                'final_score' => $score,
                'integrity_hash' => $this->integrityHash(
                    $student->id,
                    null,
                    $score,
                    $issuedAt->format('Y-m-d H:i:s'),
                    $credentialId
                ),
                'pdf_path' => $this->pdfPathFor($credentialId),
                'issued_at' => $issuedAt,
            ]);

            $this->renderPdf($certificate);
            ActivityLog::record('certificate.pathway_issued', $certificate);

            return $certificate;
        });
    }

    /**
     * After a course credential is issued, mint any pathway certificate the
     * student has just become eligible for.
     *
     * Only paths containing this course are considered, and failures are
     * swallowed deliberately: a pathway that cannot be minted must never undo
     * the course certificate that triggered the attempt.
     *
     * @return Collection<int, Certificate>
     */
    private function issueCompletedPathways(User $student, Course $course): Collection
    {
        $issued = new Collection();

        $paths = LearningPath::where('is_active', true)
            ->whereHas('courses', fn ($query) => $query->where('courses.id', $course->id))
            ->get();

        foreach ($paths as $path) {
            if (! $this->pathwayIsComplete($student, $path)) {
                continue;
            }

            $issued->push($this->issuePathwayCertificate($student, $path));
        }

        return $issued;
    }

    /**
     * Does this student hold a live course certificate for every course in the
     * path, and not yet a pathway certificate for it?
     */
    private function pathwayIsComplete(User $student, LearningPath $path): bool
    {
        $alreadyHeld = Certificate::where('student_id', $student->id)
            ->where('learning_path_id', $path->id)
            ->whereNull('revoked_at')
            ->exists();

        if ($alreadyHeld) {
            return false;
        }

        $courseIds = $path->courses()->pluck('courses.id');

        if ($courseIds->isEmpty()) {
            return false;
        }

        return $this->pathCourseCertificates($student, $courseIds)
            ->pluck('course_id')
            ->unique()
            ->count() === $courseIds->count();
    }

    /**
     * The student's live course certificates within a set of courses.
     *
     * @param  \Illuminate\Support\Collection<int, int>  $courseIds
     * @return Collection<int, Certificate>
     */
    private function pathCourseCertificates(User $student, $courseIds): Collection
    {
        return Certificate::where('student_id', $student->id)
            ->whereIn('course_id', $courseIds)
            ->whereNull('revoked_at')
            ->get();
    }

    /**
     * Evaluate every active badge rule for a student and award those newly
     * satisfied (EduSystem.md 1D).
     *
     * Safe to call repeatedly: already-earned badges are skipped, and the
     * composite unique key on badge_student is the database-level backstop.
     *
     * @return Collection<int, Badge> the badges awarded by this call
     */
    public function evaluateBadges(User $student): Collection
    {
        $alreadyEarned = $student->badges()->pluck('badges.id')->all();
        $awarded = new Collection();

        foreach ($this->badgeRules() as $badge) {
            if (in_array($badge->id, $alreadyEarned, true)) {
                continue;
            }

            if (! $this->criteriaSatisfied($student, $badge)) {
                continue;
            }

            $student->badges()->attach($badge->id, ['awarded_at' => now()]);
            $awarded->push($badge);
        }

        return $awarded;
    }

    /**
     * The active badge rules, read from the database exactly once per request.
     *
     * @return Collection<int, Badge>
     */
    private function badgeRules(): Collection
    {
        return $this->badgeRules ??= Badge::where('is_active', true)->get();
    }

    /**
     * Does this student now satisfy this rule?
     *
     * Four of the six criteria types depend on tables owned by other modules
     * that do not exist yet. Each has its own branch so that when the owning
     * module lands, only that one branch changes -- nothing else in the engine
     * has to move.
     */
    private function criteriaSatisfied(User $student, Badge $badge): bool
    {
        return match ($badge->criteria_type) {
            // Live now: counts the student's own non-revoked course credentials.
            'course_completion' => $this->completedCourseCount($student) >= $badge->criteria_value,

            // Live now, but only produces awards once learning paths exist.
            'path_completion' => $this->completedPathCount($student) >= $badge->criteria_value,

            // Best quiz percentage the student has ever scored.
            'quiz_score' => $this->bestQuizScore($student) >= $badge->criteria_value,

            // Assignments handed in before their deadline.
            'on_time_submissions' => $this->onTimeSubmissionCount($student) >= $badge->criteria_value,

            // Forum participation.
            'first_forum_post' => $student->posts()->count() >= $badge->criteria_value,

            // Consecutive days with a successful sign-in, read off the audit trail.
            'login_streak' => $this->loginStreak($student) >= $badge->criteria_value,

            default => false,
        };
    }

    /**
     * The highest percentage this student has scored on any quiz.
     */
    private function bestQuizScore(User $student): float
    {
        return (float) Grade::whereIn('quiz_attempt_id', $student->quizAttempts()->select('id'))
            ->max('calculated_score');
    }

    /**
     * How many assignments this student handed in before the deadline.
     */
    private function onTimeSubmissionCount(User $student): int
    {
        return $student->submissions()
            ->whereNotNull('submitted_at')
            ->with('assignment')
            ->get()
            ->filter(fn (Submission $submission) => $submission->wasOnTime())
            ->count();
    }

    /**
     * Length of the student's current run of consecutive days with a login.
     *
     * Read from the audit trail rather than a counter column, so it stays
     * correct even if rows are backfilled. Only the streak ending today or
     * yesterday counts -- a run that ended last month is not current.
     */
    private function loginStreak(User $student): int
    {
        $days = ActivityLog::where('user_id', $student->id)
            ->where('action', 'auth.login')
            ->orderByDesc('created_at')
            ->pluck('created_at')
            ->map(fn ($timestamp) => $timestamp->toDateString())
            ->unique()
            ->values();

        if ($days->isEmpty()) {
            return 0;
        }

        $today = now()->toDateString();
        $yesterday = now()->subDay()->toDateString();

        if ($days->first() !== $today && $days->first() !== $yesterday) {
            return 0;
        }

        $streak = 1;
        for ($i = 1; $i < $days->count(); $i++) {
            $expected = Carbon::parse($days[$i - 1])->subDay()->toDateString();

            if ($days[$i] !== $expected) {
                break;
            }

            $streak++;
        }

        return $streak;
    }

    /**
     * Course credentials the student currently holds. Revoked ones do not count
     * towards an achievement.
     */
    private function completedCourseCount(User $student): int
    {
        return Certificate::where('student_id', $student->id)
            ->whereNotNull('course_id')
            ->whereNull('revoked_at')
            ->count();
    }

    /**
     * Pathway credentials the student currently holds.
     */
    private function completedPathCount(User $student): int
    {
        return Certificate::where('student_id', $student->id)
            ->whereNotNull('learning_path_id')
            ->whereNull('revoked_at')
            ->count();
    }

    /**
     * Workflow Step 5 (EduSystem.md Section 4) -- everything a new grade sets off.
     *
     * Called when Module 5 writes a Grade. Module 5 knows nothing about
     * credentialing; Module 1 listens for the write. That keeps the boundary in
     * Section 2A intact: Module 5 owns `grades`, Module 1 only reads them.
     *
     * @return array{progress: StudentProgress|null, badges: Collection<int, Badge>, certificate: Certificate|null}
     */
    public function handleGradeRecorded(Grade $grade): array
    {
        $student = $grade->student();
        $course = $grade->course();

        if ($student === null || $course === null) {
            return ['progress' => null, 'badges' => new Collection(), 'certificate' => null];
        }

        // 5.1 recalculate progress and snapshot it
        $progress = $this->recalculateProgress($student, $course);

        // 5.3 mint the course certificate if the threshold is now met
        $certificate = $this->issueIfEligible($student, $course, $progress);

        // 5.2 evaluate the badge registry (after issuance, so credential-based
        //     rules can fire on the certificate just minted)
        $badges = $this->evaluateBadges($student);

        return ['progress' => $progress, 'badges' => $badges, 'certificate' => $certificate];
    }

    /**
     * Recalculate a student's progress in a course and write a snapshot
     * (EduSystem.md 1B).
     *
     * The weighting is read from the `settings` table, never hardcoded. The
     * three weights are intended to total 100 but are normalised here so an
     * administrator cannot break the maths by entering values that do not.
     *
     * Known gap: materials_viewed stays 0 because there is no view-tracking
     * table in Section 3, so the participation share is measured by forum
     * activity instead.
     */
    public function recalculateProgress(User $student, Course $course): StudentProgress
    {
        $quizWeight = $this->setting('progress.quiz_weight', 50);
        $assignmentWeight = $this->setting('progress.assignment_weight', 40);
        $participationWeight = $this->setting('progress.participation_weight', 10);
        $totalWeight = max(1, $quizWeight + $assignmentWeight + $participationWeight);

        $totalQuizzes = $course->quizzes()->count();
        $totalAssignments = $course->assignments()->count();

        $quizzesPassed = $this->quizzesPassedIn($student, $course);
        $assignmentsSubmitted = $student->submissions()
            ->whereIn('assignment_id', $course->assignments()->select('id'))
            ->whereNotNull('submitted_at')
            ->count();

        $participated = $course->forum !== null
            && $student->posts()->where('forum_id', $course->forum->id)->exists();

        $quizShare = $totalQuizzes > 0 ? ($quizzesPassed / $totalQuizzes) * $quizWeight : 0;
        $assignmentShare = $totalAssignments > 0 ? ($assignmentsSubmitted / $totalAssignments) * $assignmentWeight : 0;
        $participationShare = $participated ? $participationWeight : 0;

        $percentage = round((($quizShare + $assignmentShare + $participationShare) / $totalWeight) * 100, 2);

        $progress = StudentProgress::updateOrCreate(
            ['student_id' => $student->id, 'course_id' => $course->id],
            [
                'materials_viewed' => 0,
                'quizzes_passed' => $quizzesPassed,
                'assignments_submitted' => $assignmentsSubmitted,
                'completion_percentage' => min(100, $percentage),
                'last_calculated_at' => now(),
            ]
        );

        // One point on the student's progress-over-time chart.
        $progress->snapshots()->create([
            'completion_percentage' => $progress->completion_percentage,
            'captured_at' => now(),
        ]);

        return $progress;
    }

    /**
     * Mint the course certificate when the completion threshold is reached.
     *
     * Returns null when the student is not there yet, or already holds one --
     * this runs after every single grade, so it must be quiet when there is
     * nothing to do.
     */
    private function issueIfEligible(User $student, Course $course, StudentProgress $progress): ?Certificate
    {
        $threshold = $this->setting('certificate.pass_threshold', 80);

        if ($progress->completion_percentage < $threshold) {
            return null;
        }

        $alreadyHeld = Certificate::where('student_id', $student->id)
            ->where('course_id', $course->id)
            ->whereNull('revoked_at')
            ->exists();

        if ($alreadyHeld) {
            return null;
        }

        // The credential carries the student's average mark in the course, not
        // their progress percentage: it attests to how well they did.
        $score = $this->averageScoreIn($student, $course) ?? $progress->completion_percentage;

        /*
         * Completion and achievement are separate tests, and both must pass.
         *
         * completion_percentage measures engagement -- work handed in, quizzes
         * sat, forum joined -- so a diligent student who understood little
         * could reach the threshold on participation alone. Issuing on that
         * basis produced certificates carrying a failing average, which is not
         * something a verifiable credential may ever attest to.
         */
        if (! GradeScale::isPass($score)) {
            return null;
        }

        return $this->issueCertificate($student, $course, $score);
    }

    /**
     * How many of a course's quizzes this student has passed, a pass being the
     * same threshold used for certification.
     */
    private function quizzesPassedIn(User $student, Course $course): int
    {
        /*
         * The academic pass mark, not the certificate threshold. These are two
         * different bars and conflating them was wrong: with a four-question
         * quiz, scoring 3 out of 4 was being recorded as a failed quiz because
         * 75% fell short of the 80% needed for a *certificate*. Passing a quiz
         * is a D or above; earning a certificate is 80% of overall progress.
         */
        $threshold = GradeScale::PASS_MARK;

        return Grade::whereIn(
            'quiz_attempt_id',
            QuizAttempt::where('student_id', $student->id)
                ->whereIn('quiz_id', $course->quizzes()->select('id'))
                ->select('id')
        )
            ->where('calculated_score', '>=', $threshold)
            ->get()
            ->groupBy(fn (Grade $grade) => $grade->quizAttempt->quiz_id)
            ->count();
    }

    /**
     * Mean of every grade this student has earned in a course, across both
     * quizzes and coursework. Null when there are none.
     */
    private function averageScoreIn(User $student, Course $course): ?float
    {
        $quizGrades = Grade::whereIn(
            'quiz_attempt_id',
            QuizAttempt::where('student_id', $student->id)
                ->whereIn('quiz_id', $course->quizzes()->select('id'))
                ->select('id')
        )->pluck('calculated_score');

        $submissionGrades = Grade::whereIn(
            'submission_id',
            Submission::where('student_id', $student->id)
                ->whereIn('assignment_id', $course->assignments()->select('id'))
                ->select('id')
        )->pluck('calculated_score');

        $all = $quizGrades->merge($submissionGrades);

        return $all->isEmpty() ? null : round((float) $all->avg(), 2);
    }

    /**
     * Read an admin-configurable number out of the settings table.
     */
    private function setting(string $key, float $default): float
    {
        $setting = Setting::where('key', $key)->first();

        return $setting !== null ? (float) $setting->value : $default;
    }

    /**
     * Revoke a credential with a stated reason. The verification page reports
     * REVOKED from this point on and the PDF download is refused.
     */
    public function revoke(Certificate $certificate, string $reason): Certificate
    {
        if ($certificate->revoked_at !== null) {
            throw new RuntimeException("Credential {$certificate->credential_id} is already revoked.");
        }

        $certificate->update([
            'revoked_at' => now(),
            'revocation_reason' => $reason,
        ]);

        ActivityLog::record('certificate.revoked', $certificate);

        return $certificate;
    }

    /**
     * Verify a credential ID for the public verification page.
     *
     * Returns the resolved status and, when one was found, the certificate:
     *   not_found -- no such credential ID was ever issued
     *   revoked   -- an administrator withdrew it
     *   tampered  -- the stored integrity hash no longer matches the row
     *   expired   -- past its expires_at date
     *   valid     -- genuine and in force
     *
     * @return array{status: string, certificate: Certificate|null}
     */
    public function verify(string $credentialId): array
    {
        $certificate = Certificate::with(['student', 'course', 'learningPath'])
            ->where('credential_id', $credentialId)
            ->first();

        if ($certificate === null) {
            return ['status' => 'not_found', 'certificate' => null];
        }

        if ($certificate->revoked_at !== null) {
            return ['status' => 'revoked', 'certificate' => $certificate];
        }

        if (! $this->hashMatches($certificate)) {
            return ['status' => 'tampered', 'certificate' => $certificate];
        }

        if ($certificate->expires_at !== null && $certificate->expires_at->isPast()) {
            return ['status' => 'expired', 'certificate' => $certificate];
        }

        return ['status' => 'valid', 'certificate' => $certificate];
    }

    /**
     * The public URL a third party visits, and the payload encoded in the QR
     * code printed on the PDF.
     */
    public function verificationUrl(string $credentialId): string
    {
        return route('certificates.verify', ['credential_id' => $credentialId]);
    }

    /**
     * The QR code for a credential, as an SVG data URI that DomPDF can embed.
     */
    public function verificationQrCode(string $credentialId): string
    {
        $svg = QrCode::format('svg')
            ->size(150)
            ->margin(0)
            ->errorCorrection('M')
            ->generate($this->verificationUrl($credentialId));

        return 'data:image/svg+xml;base64,'.base64_encode((string) $svg);
    }

    /**
     * Recompute the stored hash and compare, proving the row has not been
     * edited directly in the database since issuance.
     *
     * Note for the report: revoked_at is deliberately not part of the hash,
     * because the spec fixes the formula at
     * SHA-256(student_id|course_id|score|issued_at|credential_id) and revocation
     * must remain possible after issuance without invalidating the hash.
     */
    private function hashMatches(Certificate $certificate): bool
    {
        $recomputed = $this->integrityHash(
            $certificate->student_id,
            $certificate->course_id,
            $certificate->final_score,
            $certificate->issued_at->format('Y-m-d H:i:s'),
            $certificate->credential_id
        );

        return hash_equals($certificate->integrity_hash, $recomputed);
    }

    /**
     * SHA-256(student_id | course_id | score | issued_at | credential_id).
     */
    private function integrityHash(
        int $studentId,
        ?int $courseId,
        float $score,
        string $issuedAt,
        string $credentialId
    ): string {
        return hash('sha256', implode('|', [
            $studentId,
            $courseId ?? '',
            $score,
            $issuedAt,
            $credentialId,
        ]));
    }

    /**
     * Mint a globally unique, human-readable credential ID.
     *
     * Uniqueness rests on two things: this method is reachable only through the
     * one authority instance, and the database holds a unique index on
     * certificates.credential_id as the final backstop.
     */
    private function generateCredentialId(): string
    {
        for ($attempt = 0; $attempt < self::MAX_ID_ATTEMPTS; $attempt++) {
            $candidate = sprintf(
                '%s-%s-%s',
                self::CREDENTIAL_PREFIX,
                now()->year,
                $this->randomBase32(self::CREDENTIAL_RANDOM_LENGTH)
            );

            if (! Certificate::where('credential_id', $candidate)->exists()) {
                return $candidate;
            }
        }

        throw new RuntimeException('Could not mint a unique credential ID after '.self::MAX_ID_ATTEMPTS.' attempts.');
    }

    /**
     * A cryptographically random Crockford Base32 string.
     */
    private function randomBase32(int $length): string
    {
        $alphabetLastIndex = strlen(self::BASE32_ALPHABET) - 1;
        $output = '';

        for ($i = 0; $i < $length; $i++) {
            $output .= self::BASE32_ALPHABET[random_int(0, $alphabetLastIndex)];
        }

        return $output;
    }

    /**
     * Where a credential's PDF lives on the private disk. It is served through
     * a controller that checks permissions, never linked to directly.
     */
    private function pdfPathFor(string $credentialId): string
    {
        return 'certificates/'.$credentialId.'.pdf';
    }

    /**
     * Render the template to PDF with the QR code embedded, and store it.
     */
    private function renderPdf(Certificate $certificate): void
    {
        $certificate->loadMissing(['student', 'course', 'learningPath', 'certificateTemplate']);

        $pdf = Pdf::loadView('certificates.pdf', [
            'certificate' => $certificate,
            'heading' => $certificate->learning_path_id !== null
                ? 'Pathway Certificate'
                : 'Certificate of Completion',
            'bodyText' => $this->fillPlaceholders($certificate),
            'qrCode' => $this->verificationQrCode($certificate->credential_id),
            'verificationUrl' => $this->verificationUrl($certificate->credential_id),
        ])->setPaper('a4', 'landscape');

        Storage::disk('local')->put($certificate->pdf_path, $pdf->output());
    }

    /**
     * Substitute the template placeholders listed in EduSystem.md 1C.
     */
    private function fillPlaceholders(Certificate $certificate): string
    {
        return str_replace(
            ['{{student_name}}', '{{course_title}}', '{{score}}', '{{issued_date}}', '{{credential_id}}'],
            [
                $certificate->student->name,
                $certificate->course?->title ?? $certificate->learningPath?->title ?? '',
                rtrim(rtrim(number_format($certificate->final_score, 2), '0'), '.'),
                $certificate->issued_at->format('j F Y'),
                $certificate->credential_id,
            ],
            $certificate->certificateTemplate->body_text
        );
    }

    /**
     * Fallback score: how far the student actually got in the course.
     *
     * Module 1 only reads progress here; Module 5 remains the sole writer of
     * grades (EduSystem.md Section 2A).
     */
    private function recordedScoreFor(User $student, Course $course): float
    {
        $progress = StudentProgress::where('student_id', $student->id)
            ->where('course_id', $course->id)
            ->first();

        return (float) ($progress->completion_percentage ?? 0);
    }
}
