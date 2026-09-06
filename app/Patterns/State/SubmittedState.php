<?php

/**
 * LearnSync -- State pattern (behavioural)
 *
 * Module 5: Academic Progress Analytics
 *
 * @author Ong Kwong Wei
 */

namespace App\Patterns\State;

use App\Models\Grade;
use App\Models\Submission;

/**
 * Handed in and locked. The student can no longer touch the file; the
 * instructor can now mark it.
 */
class SubmittedState implements SubmissionState
{
    public function name(): string
    {
        return 'submitted';
    }

    public function label(): string
    {
        return 'already submitted';
    }

    public function canUpdateFile(): bool
    {
        return false;
    }

    public function canSubmit(): bool
    {
        return false;
    }

    public function canAssignGrade(): bool
    {
        return true;
    }

    public function updateFile(Submission $submission, string $path): void
    {
        throw IllegalSubmissionTransition::because('replace the file', $this);
    }

    public function submit(Submission $submission): void
    {
        throw IllegalSubmissionTransition::because('submit again', $this);
    }

    /**
     * Write the authoritative Grade and move to graded.
     *
     * Module 5 is the sole writer of `grades` (EduSystem.md Section 2A). The
     * Grade model's saved event is what wakes the CredentialAuthority.
     */
    public function assignGrade(Submission $submission, float $score): Grade
    {
        $grade = Grade::updateOrCreate(
            ['submission_id' => $submission->id],
            ['calculated_score' => $score]
        );

        $submission->update(['state' => (new GradedState())->name()]);

        return $grade;
    }
}
