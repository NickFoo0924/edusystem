<?php

namespace App\Patterns\Facade\Subsystem;

use App\Models\Course;
use App\Models\Grade;
use App\Models\QuizAttempt;
use App\Models\Setting;
use App\Models\StudentProgress;
use App\Models\Submission;
use App\Models\User;
use App\Support\GradeScale;

/**
 * SUBSYSTEM COMPONENT -- progress arithmetic and the marks behind it
 * (EduSystem.md 1B).
 *
 * One of the five collaborators hidden behind the CredentialAuthority Facade.
 * Module 1 only ever reads `grades` here; Module 5 remains their sole writer
 * (EduSystem.md Section 2A).
 */
class ProgressCalculator
{
    /**
     * Recalculate a student's progress in a course and write a snapshot.
     *
     * The weighting is read from the `settings` table, never hardcoded. The
     * three weights are intended to total 100 but are normalised here so an
     * administrator cannot break the maths by entering values that do not.
     *
     * Known gap: materials_viewed stays 0 because there is no view-tracking
     * table in Section 3, so the participation share is measured by forum
     * activity instead.
     */
    public function recalculate(User $student, Course $course): StudentProgress
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
     * The completion percentage at which a course certificate becomes due.
     */
    public function passThreshold(): float
    {
        return $this->setting('certificate.pass_threshold', 80);
    }

    /**
     * How many of a course's quizzes this student has passed, a pass being the
     * academic pass mark.
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
    public function averageScoreIn(User $student, Course $course): ?float
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
     * Fallback score: how far the student actually got in the course.
     */
    public function recordedScoreFor(User $student, Course $course): float
    {
        $progress = StudentProgress::where('student_id', $student->id)
            ->where('course_id', $course->id)
            ->first();

        return (float) ($progress->completion_percentage ?? 0);
    }

    /**
     * Read an admin-configurable number out of the settings table.
     */
    private function setting(string $key, float $default): float
    {
        $setting = Setting::where('key', $key)->first();

        return $setting !== null ? (float) $setting->value : $default;
    }
}
