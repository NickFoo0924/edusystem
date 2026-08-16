<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Grade;
use App\Models\Submission;
use App\Support\GradeScale;
use Illuminate\Http\Request;
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
        $courses = $user->can('analytics.view_system')
            ? Course::with('instructor')->withCount('students')->orderBy('title')->get()
            : $user->coursesTeaching()->with('instructor')->withCount('students')->orderBy('title')->get();

        return view('analytics.index', [
            'courses' => $courses->map(fn (Course $course) => $this->statisticsFor($course)),
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
        ];
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
}
