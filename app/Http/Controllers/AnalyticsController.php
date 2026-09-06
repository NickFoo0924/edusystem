<?php

/**
 * LearnSync -- HTTP controller
 *
 * Module 5: Academic Progress Analytics
 *
 * @author Ong Kwong Wei
 */

namespace App\Http\Controllers;

use App\Models\Certificate;
use App\Models\Course;
use App\Models\ProgressSnapshot;
use App\Models\StudentProgress;
use App\Models\Grade;
use App\Models\Submission;
use App\Support\Api\CredentialStatusClient;
use App\Support\GradeScale;
use DOMDocument;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * MODULE 5 (Ong Kwong Wei) -- the instructor and administrator grading view.
 *
 * Section 2A splits progress reporting in two: Module 1 owns the student's own
 * credential-oriented view, and Module 5 owns this -- class averages, grade
 * distributions and submission turnaround. Nothing here is per-student
 * motivational; it is about how a cohort is performing.
 */
class AnalyticsController extends Controller
{
    /**
     * Module 5's client for Module 1's credential service, injected so the
     * report can confirm credentials without re-implementing Module 1's
     * revocation and integrity checks.
     */
    public function __construct(private CredentialStatusClient $credentials)
    {
    }

    // The distribution is grouped by letter grade (GradeScale), not by
    // arbitrary mark ranges, so it reads the way a results sheet does.

    public function index(Request $request): View
    {
        $user = $request->user();

        abort_unless(
            $user->can('progress.view_students') || $user->can('analytics.view_system'),
            403
        );

        // An instructor sees their own courses; an administrator sees all.
        $courses = $this->visibleCourses($user);

        return view('analytics.index', [
            'courses' => $courses->map(fn (Course $course) => $this->statisticsFor($course)),
            // Null when the pipeline could not run; the view simply omits the
            // card rather than showing a broken one.
            'chart' => $this->renderChart($this->buildXml($courses)),
        ]);
    }

    /**
     * Everything the screen shows about one course.
     *
     * @return array<string, mixed>
     */
    private function statisticsFor(Course $course): array
    {
        $scores = $this->scoresFor($course);
        $submissions = Submission::whereIn('assignment_id', $course->assignments()->select('id'))->get();

        $average = $scores->isEmpty() ? null : round($scores->avg(), 2);

        return [
            'course' => $course,
            'graded' => $scores->count(),
            'average' => $average,
            'averageLetter' => $average === null ? null : GradeScale::letterFor($average),
            'highest' => $scores->max(),
            'highestLetter' => $scores->isEmpty() ? null : GradeScale::letterFor($scores->max()),
            'lowest' => $scores->min(),
            'lowestLetter' => $scores->isEmpty() ? null : GradeScale::letterFor($scores->min()),
            // How many passed at D or above.
            'passed' => $scores->filter(fn ($s) => GradeScale::isPass($s))->count(),
            'distribution' => $this->distribution($scores),
            'submitted' => $submissions->whereNotNull('submitted_at')->count(),
            'awaiting' => $submissions->where('state', 'submitted')->count(),
            'onTime' => $submissions->filter(fn (Submission $s) => $s->wasOnTime())->count(),
            'turnaround' => $this->averageTurnaroundHours($submissions),
            'credentials' => $this->credentialsConfirmedFor($course),
        ];
    }

    /**
     * MODULE 5 CONSUMES MODULE 1's WEB SERVICE.
     *
     * How many credentials issued for this course are still genuinely valid.
     *
     * Module 5 does not read `certificates` and decide for itself. Whether a
     * credential is live depends on revocation and on an integrity hash that
     * Module 1 owns, so re-implementing that check here would mean two
     * versions of a security rule, and the second one would be wrong the
     * first time the first one changed. Module 5 asks and counts the answers.
     *
     * detailFlag 1 is used, so Module 1 returns a status and nothing about
     * the holder. A report that counts credentials has no business receiving
     * names and marks.
     *
     * Each check is a separate HTTP call, so the number of them is bounded.
     * A report that takes twenty seconds to load is a report nobody opens,
     * and confirming a sample is enough to show the credentials in a course
     * are live. `issued` is still the true total, counted locally, so the
     * figure never overstates what was actually checked.
     *
     * @return array{issued: int, checked: int, confirmed: int}|null
     */
    private const CREDENTIALS_TO_CONFIRM = 5;

    private function credentialsConfirmedFor(Course $course): ?array
    {
        $credentialIds = Certificate::where('course_id', $course->id)
            ->pluck('credential_id');

        if ($credentialIds->isEmpty()) {
            return null;
        }

        $toCheck = $credentialIds->take(self::CREDENTIALS_TO_CONFIRM);
        $confirmed = 0;
        $answered = 0;

        foreach ($toCheck as $credentialId) {
            $data = $this->credentials->status($credentialId);

            if ($data === null) {
                // Module 1 is unreachable. Skip rather than counting a
                // credential the authority did not actually confirm.
                continue;
            }

            $answered++;

            if (($data['credentialStatus'] ?? null) === 'VALID') {
                $confirmed++;
            }
        }

        // Null when Module 1 answered nothing at all, so the view omits the
        // figure instead of printing a misleading zero.
        return $answered > 0
            ? [
                'issued' => $credentialIds->count(),
                'checked' => $answered,
                'confirmed' => $confirmed,
            ]
            : null;
    }

    /**
     * Every grade earned in a course, from both quizzes and coursework.
     */
    private function scoresFor(Course $course)
    {
        $quizScores = Grade::whereIn('quiz_attempt_id', function ($query) use ($course) {
            $query->select('id')->from('quiz_attempts')
                ->whereIn('quiz_id', $course->quizzes()->select('id'));
        })->pluck('calculated_score');

        $submissionScores = Grade::whereIn('submission_id', function ($query) use ($course) {
            $query->select('id')->from('submissions')
                ->whereIn('assignment_id', $course->assignments()->select('id'));
        })->pluck('calculated_score');

        return $quizScores->merge($submissionScores);
    }

    /**
     * How many grades fell into each letter family, A through F.
     *
     * Families rather than the full eleven letters: five bars carry the same
     * information and stay readable.
     *
     * @return array<string, int>
     */
    private function distribution($scores): array
    {
        $counts = [];

        foreach (GradeScale::families() as $family) {
            $counts[$family] = $scores
                ->filter(fn ($score) => GradeScale::familyFor($score) === $family)
                ->count();
        }

        return $counts;
    }

    /**
     * Mean hours between a student submitting and the grade being recorded.
     *
     * Null when nothing has been marked yet -- an average of no turnaround is
     * meaningless, and showing 0 would imply instant marking.
     */
    private function averageTurnaroundHours($submissions): ?float
    {
        $hours = $submissions
            ->filter(fn (Submission $s) => $s->submitted_at !== null && $s->state === 'graded' && $s->grade !== null)
            ->map(fn (Submission $s) => $s->submitted_at->diffInMinutes($s->grade->created_at) / 60);

        return $hours->isEmpty() ? null : round($hours->avg(), 1);
    }

    /*
     * ------------------------------------------------------------------
     * THE XML PIPELINE
     *
     * Eloquent -> DOMDocument -> XSD validation -> XSLT -> SVG.
     *
     * The syllabus covers XML, schema validation and XSL transformation
     * (Chapters 4A and 4B) and nothing else in this system exercises them.
     * SVG is itself an XML vocabulary, so drawing a chart this way is a real
     * XML-to-XML transformation rather than an exercise invented to tick a
     * box, and the document produced on the way doubles as a data export.
     *
     * DOMDocument is the DOM half of the DOM-versus-SAX pair the module
     * teaches: a tree held in memory with a read/write API, which is what
     * building a document needs. SAX is read-only and streaming, so it could
     * not do this job.
     * ------------------------------------------------------------------
     */

    /**
     * The analytics document: one <course> per course, each holding the
     * cohort's average completion on each date a snapshot was taken.
     *
     * Built through DOM rather than by joining strings, so a course title
     * containing & or < cannot produce a malformed document.
     *
     * @param  \Illuminate\Support\Collection<int, Course>  $courses
     */
    private function buildXml($courses): DOMDocument
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $document->formatOutput = true;

        $root = $document->createElement('analytics');
        // Atom format, because the schema types this as xs:dateTime and
        // "2026-08-24 10:15:00" is not one -- xs:dateTime wants the T.
        $root->setAttribute('generated', now()->toAtomString());
        $document->appendChild($root);

        foreach ($courses as $course) {
            $element = $document->createElement('course');
            $element->setAttribute('code', $course->code);
            $element->setAttribute('title', $course->title);
            $element->setAttribute('students', (string) ($course->students_count ?? 0));

            foreach ($this->cohortTrend($course) as $date => $average) {
                $point = $document->createElement('point');
                $point->setAttribute('date', $date);
                $point->setAttribute('average', (string) $average);
                $element->appendChild($point);
            }

            // The same distribution the page prints as CSS bars, carried in
            // the export so the document is a complete picture of the course.
            foreach ($this->distribution($this->scoresFor($course)) as $letter => $count) {
                $band = $document->createElement('band');
                $band->setAttribute('letter', $letter);
                $band->setAttribute('count', (string) $count);
                $element->appendChild($band);
            }

            $root->appendChild($element);
        }

        return $document;
    }

    /**
     * Average completion across the cohort, per date a snapshot exists for.
     *
     * Read through the StudentProgress rows of the course, so this stays
     * Eloquent throughout -- Section 5 forbids raw SQL.
     *
     * @return array<string, float>  Y-m-d => percentage, oldest first
     */
    private function cohortTrend(Course $course): array
    {
        $progressIds = StudentProgress::where('course_id', $course->id)->pluck('id');

        if ($progressIds->isEmpty()) {
            return [];
        }

        return ProgressSnapshot::whereIn('student_progress_id', $progressIds)
            ->orderBy('captured_at')
            ->get()
            ->groupBy(fn (ProgressSnapshot $snapshot) => $snapshot->captured_at->toDateString())
            ->map(fn ($sameDay) => round($sameDay->avg('completion_percentage'), 2))
            ->all();
    }

    /**
     * Check a document against resources/xml/analytics.xsd.
     *
     * A schema fault must never take the analytics page down, so the errors
     * are logged and the caller renders without the chart.
     */
    private function validates(DOMDocument $document): bool
    {
        $previous = libxml_use_internal_errors(true);
        $valid = $document->schemaValidate(resource_path('xml/analytics.xsd'));

        foreach (libxml_get_errors() as $error) {
            Log::warning('Analytics XML failed schema validation: '.trim($error->message));
        }

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $valid;
    }

    /**
     * Run the document through the stylesheet and return the SVG it produces.
     *
     * Returns null if anything in the pipeline fails, which the view treats as
     * "no chart" rather than as an error.
     */
    private function renderChart(DOMDocument $document): ?string
    {
        if (! class_exists(\XSLTProcessor::class)) {
            Log::warning('The XSL extension is not enabled, so the analytics chart was skipped.');

            return null;
        }

        if (! $this->validates($document)) {
            return null;
        }

        $stylesheet = new DOMDocument();
        $stylesheet->load(resource_path('xml/analytics-chart.xsl'));

        $processor = new \XSLTProcessor();
        $processor->importStylesheet($stylesheet);

        $svg = $processor->transformToXml($document);

        return $svg === false ? null : $svg;
    }

    /**
     * The same document, served as a download.
     *
     * This is what stops the XML being a throwaway intermediate step: it is a
     * data export in its own right, and the stylesheet happens to consume the
     * same thing.
     */
    public function exportXml(Request $request): Response
    {
        $user = $request->user();

        abort_unless(
            $user->can('progress.view_students') || $user->can('analytics.view_system'),
            403
        );

        $document = $this->buildXml($this->visibleCourses($user));

        return response($document->saveXML(), 200, [
            'Content-Type' => 'application/xml',
            'Content-Disposition' => 'attachment; filename="analytics.xml"',
        ]);
    }

    /**
     * The courses this user may report on: their own, or all of them.
     *
     * @return \Illuminate\Support\Collection<int, Course>
     */
    private function visibleCourses($user)
    {
        return $user->can('analytics.view_system')
            ? Course::with('instructor')->withCount('students')->orderBy('title')->get()
            : $user->coursesTeaching()->with('instructor')->withCount('students')->orderBy('title')->get();
    }
}
