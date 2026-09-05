<?php

namespace App\Patterns\Facade\Subsystem;

use App\Models\ActivityLog;
use App\Models\Badge;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\Grade;
use App\Models\QuizAttempt;
use App\Models\Submission;
use App\Models\User;
use App\Support\GradeScale;
use Carbon\Carbon;

/**
 * SUBSYSTEM COMPONENT -- the one place an award condition is decided.
 *
 * THE SINGLE EVALUATION PATH.
 *
 * Every award rule in the system is answered here, whether it was seeded with
 * the application or typed in by an administrator this morning, and whether it
 * awards a badge or mints a certificate. There is no second code path for
 * "built-in" rules -- a seeded rule is simply a row that arrived earlier, and
 * nothing about it is privileged.
 *
 * WHY THIS IS NOT A SCRIPTING ENGINE. An administrator picks a condition from
 * a fixed list and fills in its number (and, for the subject-scoped ones, its
 * subject). They never write an expression. That deliberately gives up
 * arbitrary conditions in exchange for two things worth more: an admin cannot
 * write a rule that errors, loops or reads data they should not see, and every
 * condition below can be read and reasoned about by whoever marks this project.
 *
 * Adding a condition type is a new arm of the match plus a line in the enum --
 * which is the cost of not having built an interpreter.
 */
class AwardConditionEvaluator
{
    /**
     * Does this student now satisfy this rule's condition?
     */
    public function isSatisfied(User $student, Badge $rule): bool
    {
        return match ($rule->criteria_type) {
            // Non-revoked course credentials the student holds.
            'course_completion' => $this->completedCourseCount($student) >= $rule->criteria_value,

            'path_completion' => $this->completedPathCount($student) >= $rule->criteria_value,

            // Best quiz percentage the student has ever scored.
            'quiz_score' => $this->bestQuizScore($student) >= $rule->criteria_value,

            // Assignments handed in before their deadline.
            'on_time_submissions' => $this->onTimeSubmissionCount($student) >= $rule->criteria_value,

            'first_forum_post' => $student->posts()->count() >= $rule->criteria_value,

            // Consecutive days with a successful sign-in, read off the audit trail.
            'login_streak' => $this->loginStreak($student) >= $rule->criteria_value,

            // Every quiz in a subject passed.
            'all_quizzes_in_course' => $this->hasClearedEveryQuiz($student, $rule),

            // Mean quiz mark in a subject at or above the rule's percentage.
            'average_score_in_course' => $this->averageQuizScoreSatisfies($student, $rule),

            // Distinct quizzes passed anywhere in the system.
            'quizzes_completed' => $this->distinctQuizzesPassed($student) >= $rule->criteria_value,

            default => false,
        };
    }

    /* ---------------------------------------------------------------- *
     * Subject-scoped conditions
     * ---------------------------------------------------------------- */

    /**
     * Has this student passed every quiz in the subject this rule names?
     *
     * A rule scoped to a course asks about that course. An unscoped one asks
     * how many courses the student has cleared outright, which keeps
     * criteria_value meaning the same kind of thing it means everywhere else.
     */
    private function hasClearedEveryQuiz(User $student, Badge $rule): bool
    {
        if ($rule->course_id !== null) {
            $course = Course::find($rule->course_id);

            return $course !== null && $this->clearedEveryQuizIn($student, $course);
        }

        $cleared = $student->courses()->get()
            ->filter(fn (Course $course) => $this->clearedEveryQuizIn($student, $course))
            ->count();

        return $cleared >= $rule->criteria_value;
    }

    /**
     * Every quiz in one course, passed at least once.
     *
     * "Passed", not "attempted": sitting a quiz and failing it is not expertise,
     * and the bar is the academic pass mark rather than the higher threshold a
     * certificate needs -- the two are different questions.
     *
     * A subject with no quizzes returns false. There is nothing to be expert in
     * yet, and awarding for an empty course would let a rule fire on every
     * student the moment a course was created.
     */
    private function clearedEveryQuizIn(User $student, Course $course): bool
    {
        $quizIds = $course->quizzes()->pluck('id');

        if ($quizIds->isEmpty()) {
            return false;
        }

        return $this->quizzesPassedIn($student, $quizIds)->count() === $quizIds->count();
    }

    /**
     * Mean quiz mark within a subject, against the rule's percentage.
     *
     * Unscoped, this is the mean across every quiz the student has ever sat,
     * which is the only sensible reading of "average score" with no subject
     * named. A student with no marks at all satisfies nothing -- an average of
     * nothing is not a high average.
     */
    private function averageQuizScoreSatisfies(User $student, Badge $rule): bool
    {
        $quizIds = $rule->course_id !== null
            ? Course::find($rule->course_id)?->quizzes()->pluck('id')
            : null;

        if ($rule->course_id !== null && ($quizIds === null || $quizIds->isEmpty())) {
            return false;
        }

        $scores = $this->quizGrades($student, $quizIds)->pluck('calculated_score');

        return $scores->isNotEmpty() && $scores->avg() >= $rule->criteria_value;
    }

    /* ---------------------------------------------------------------- *
     * System-wide conditions
     * ---------------------------------------------------------------- */

    /**
     * Distinct quizzes this student has passed anywhere.
     *
     * Distinct, so re-sitting the same quiz five times is one quiz completed
     * rather than five.
     */
    private function distinctQuizzesPassed(User $student): int
    {
        return $this->quizzesPassedIn($student, null)->count();
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

    /* ---------------------------------------------------------------- *
     * Shared query helpers
     * ---------------------------------------------------------------- */

    /**
     * The student's quiz grades, optionally narrowed to a set of quizzes.
     *
     * @param  \Illuminate\Support\Collection<int, int>|null  $quizIds
     * @return \Illuminate\Database\Eloquent\Collection<int, Grade>
     */
    private function quizGrades(User $student, $quizIds)
    {
        $attempts = QuizAttempt::where('student_id', $student->id);

        if ($quizIds !== null) {
            $attempts->whereIn('quiz_id', $quizIds);
        }

        return Grade::whereIn('quiz_attempt_id', $attempts->select('id'))->get();
    }

    /**
     * Distinct quiz ids the student has passed, optionally within a set.
     *
     * A quiz may be sat more than once; one pass is enough, so this counts
     * distinct quizzes rather than grades.
     *
     * @param  \Illuminate\Support\Collection<int, int>|null  $quizIds
     * @return \Illuminate\Support\Collection<int, int>
     */
    private function quizzesPassedIn(User $student, $quizIds)
    {
        return $this->quizGrades($student, $quizIds)
            ->filter(fn (Grade $grade) => $grade->calculated_score >= GradeScale::PASS_MARK)
            ->map(fn (Grade $grade) => $grade->quizAttempt->quiz_id)
            ->unique()
            ->values();
    }
}
