<?php

/**
 * LearnSync -- Facade pattern (structural)
 *
 * Module 1: Identity, Access & Digital Credentialing
 *
 * @author Serena Lim Sze Kee
 */

namespace App\Patterns\Facade;

use App\Models\ActivityLog;
use App\Models\Badge;
use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\Course;
use App\Models\Grade;
use App\Models\LearningPath;
use App\Models\StudentProgress;
use App\Models\User;
use App\Patterns\Facade\Subsystem\AwardConditionEvaluator;
use App\Patterns\Facade\Subsystem\BadgeRuleEvaluator;
use App\Patterns\Facade\Subsystem\CertificateRenderer;
use App\Patterns\Facade\Subsystem\CredentialIdGenerator;
use App\Patterns\Facade\Subsystem\IntegrityHasher;
use App\Patterns\Facade\Subsystem\ProgressCalculator;
use App\Support\GradeScale;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * MODULE 1 DESIGN PATTERN -- FACADE (Structural).
 *
 * Issuing a credential is not one operation. It means minting a collision-free
 * human-readable ID, sealing the row with a SHA-256 integrity hash, rendering a
 * DomPDF document with an embedded QR code and substituted template
 * placeholders, writing it to a private disk, recalculating weighted progress
 * against admin-configurable settings, snapshotting that progress for the
 * student's chart, evaluating every active badge rule, checking whether the
 * course just completed a learning path, and writing the audit trail.
 *
 * That is five distinct collaborators and four third-party libraries. The
 * Facade gives the rest of the system ONE object with a small, stable
 * vocabulary -- issueCertificate, revoke, verify -- and hides all of it. A
 * controller wanting to issue a credential (CertificateController) writes one
 * line and imports neither DomPDF nor the QR encoder nor the settings table.
 *
 * The subsystem it fronts, all in App\Patterns\Facade\Subsystem:
 *   CredentialIdGenerator -- mints LS-{YEAR}-{BASE32}, retrying on collision
 *   IntegrityHasher       -- computes and re-verifies the tamper seal
 *   CertificateRenderer   -- DomPDF, QR encoding, placeholders, storage
 *   ProgressCalculator    -- the weighted completion arithmetic and marks
 *   BadgeRuleEvaluator    -- the badge rule registry and its criteria
 *
 * Each collaborator remains usable on its own; the Facade adds a simpler entry
 * point without hiding them away permanently, which is the pattern's own
 * stated intent.
 *
 * CONSTRUCTION: ordinary dependency injection. The constructor is public, the
 * class holds no static state, and nothing anywhere calls a static accessor to
 * reach it. CredentialServiceProvider registers it as request-scoped so the
 * badge registry is read once per request -- that is container lifetime
 * management, not the Singleton pattern: a test can construct as many
 * independent authorities as it likes.
 *
 * No pattern logic lives in a controller (EduSystem.md Section 6).
 */
class CredentialAuthority
{
    public function __construct(
        private CredentialIdGenerator $ids,
        private IntegrityHasher $hasher,
        private CertificateRenderer $renderer,
        private ProgressCalculator $progress,
        private BadgeRuleEvaluator $badges,
        private AwardConditionEvaluator $conditions,
    ) {
    }

    /**
     * Issue a course certificate to a student.
     *
     * $finalScore is optional: when the grade event in Module 5 calls this it
     * passes the authoritative score, and otherwise the authority falls back to
     * the student's recorded completion percentage.
     *
     * $rule is optional and names the administrator-defined award rule that
     * triggered this issuance, if one did. Its only effect is to choose the
     * template: a rule may nominate its own design, and falls back to the
     * active default when it does not. Added as a trailing optional parameter
     * so every existing caller keeps working unchanged.
     *
     * @throws RuntimeException if the student already holds a live certificate
     *                          for this course, or no active template exists.
     */
    public function issueCertificate(
        User $student,
        Course $course,
        ?float $finalScore = null,
        ?Badge $rule = null
    ): Certificate {
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

        // A rule may nominate its own design; otherwise the active default.
        $template = $rule?->certificateTemplate
            ?? CertificateTemplate::where('is_active', true)->first();

        if ($template === null) {
            throw new RuntimeException('No active certificate template exists to render from.');
        }

        $score = $finalScore ?? $this->progress->recordedScoreFor($student, $course);

        // One transaction so that minting the ID, writing the row and rendering
        // the PDF either all succeed or all roll back. A half-issued credential
        // would be unverifiable.
        return DB::transaction(function () use ($student, $course, $template, $score) {
            $credentialId = $this->ids->generate();
            $issuedAt = now();

            $certificate = Certificate::create([
                'student_id' => $student->id,
                'course_id' => $course->id,
                'learning_path_id' => null,
                'certificate_template_id' => $template->id,
                'credential_id' => $credentialId,
                'final_score' => $score,
                'integrity_hash' => $this->hasher->hash(
                    $student->id,
                    $course->id,
                    $score,
                    $issuedAt->format('Y-m-d H:i:s'),
                    $credentialId
                ),
                'pdf_path' => $this->renderer->pdfPathFor($credentialId),
                'issued_at' => $issuedAt,
            ]);

            $this->renderer->render($certificate);

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
            $credentialId = $this->ids->generate();
            $issuedAt = now();

            $certificate = Certificate::create([
                'student_id' => $student->id,
                'course_id' => null,
                'learning_path_id' => $path->id,
                'certificate_template_id' => $template->id,
                'credential_id' => $credentialId,
                'final_score' => $score,
                'integrity_hash' => $this->hasher->hash(
                    $student->id,
                    null,
                    $score,
                    $issuedAt->format('Y-m-d H:i:s'),
                    $credentialId
                ),
                'pdf_path' => $this->renderer->pdfPathFor($credentialId),
                'issued_at' => $issuedAt,
            ]);

            $this->renderer->render($certificate);
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
     * Delegated to the badge subsystem; the signature is unchanged so every
     * existing caller keeps working.
     *
     * @return Collection<int, Badge> the badges awarded by this call
     */
    public function evaluateBadges(User $student): Collection
    {
        return $this->badges->evaluate($student);
    }

    /**
     * Workflow Step 5 (EduSystem.md Section 4) -- everything a new grade sets off.
     *
     * Called when Module 5 writes a Grade. Module 5 knows nothing about
     * credentialing; Module 1 listens. That keeps the boundary in Section 2A
     * intact: Module 5 owns `grades`, Module 1 only reads them.
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

        // 5.3 mint the course certificate if the built-in threshold is now met
        $certificate = $this->issueIfEligible($student, $course, $progress);

        /*
         * 5.3b any administrator-defined certificate rule that has just become
         *      true. Run after the built-in rule and before badges, so a
         *      badge whose condition counts credentials can see everything
         *      minted in this pass.
         *
         *      Deliberately called from here and never from inside
         *      issueCertificate(): issuance evaluates badges as its last step,
         *      and if it evaluated certificate rules too, a rule that stays
         *      satisfied after issuing would call itself back.
         */
        $ruleCertificates = $this->evaluateCertificateRules($student);

        // 5.2 evaluate the badge registry (after issuance, so credential-based
        //     rules can fire on the certificates just minted)
        $badges = $this->evaluateBadges($student);

        return [
            'progress' => $progress,
            'badges' => $badges,
            'certificate' => $certificate ?? $ruleCertificates->first(),
        ];
    }

    /**
     * Mint a credential for any active administrator-defined certificate rule
     * the student now satisfies.
     *
     * This is what makes certificate issuance configurable rather than
     * hardcoded. The built-in rule in issueIfEligible() -- completion past the
     * settings threshold, with a passing average -- still exists and still runs;
     * these are additional conditions an administrator wrote, evaluated through
     * exactly the same AwardConditionEvaluator that answers badge rules.
     *
     * A certificate rule always names a subject, because a credential attests
     * to something in particular. Rules with no course are skipped rather than
     * guessed at.
     *
     * @return Collection<int, Certificate>
     */
    public function evaluateCertificateRules(User $student): Collection
    {
        $issued = new Collection();

        $rules = Badge::where('is_active', true)
            ->where('award_type', 'certificate')
            ->whereNotNull('course_id')
            ->get();

        foreach ($rules as $rule) {
            $course = Course::find($rule->course_id);

            if ($course === null) {
                continue;
            }

            // One live credential per student per course, whichever rule or
            // threshold produced it.
            $alreadyHeld = Certificate::where('student_id', $student->id)
                ->where('course_id', $course->id)
                ->whereNull('revoked_at')
                ->exists();

            if ($alreadyHeld || ! $this->conditions->isSatisfied($student, $rule)) {
                continue;
            }

            $score = $this->progress->averageScoreIn($student, $course)
                ?? $this->progress->recordedScoreFor($student, $course);

            $issued->push($this->issueCertificate($student, $course, $score, $rule));
        }

        return $issued;
    }

    /**
     * Recalculate a student's progress in a course and write a snapshot
     * (EduSystem.md 1B).
     *
     * Delegated to the progress subsystem; the signature is unchanged.
     */
    public function recalculateProgress(User $student, Course $course): StudentProgress
    {
        return $this->progress->recalculate($student, $course);
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
        if ($progress->completion_percentage < $this->progress->passThreshold()) {
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
        $score = $this->progress->averageScoreIn($student, $course) ?? $progress->completion_percentage;

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

        if (! $this->hasher->matches($certificate)) {
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
        return $this->renderer->verificationUrl($credentialId);
    }

    /**
     * The QR code for a credential, as an SVG data URI that DomPDF can embed.
     */
    public function verificationQrCode(string $credentialId): string
    {
        return $this->renderer->verificationQrCode($credentialId);
    }
}
