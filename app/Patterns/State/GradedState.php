<?php

namespace App\Patterns\State;

use App\Models\Grade;
use App\Models\Submission;

/**
 * Final. Neither the work nor the mark may change.
 *
 * This is the state that protects the credentialing chain: a certificate is
 * issued off the back of a grade, so letting a graded submission be edited
 * afterwards would leave a credential attesting to something that no longer
 * matches the record.
 */
class GradedState implements SubmissionState
{
    public function name(): string
    {
        return 'graded';
    }

    public function label(): string
    {
        return 'already graded';
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
        return false;
    }

    public function updateFile(Submission $submission, string $path): void
    {
        throw IllegalSubmissionTransition::because('replace the file', $this);
    }

    public function submit(Submission $submission): void
    {
        throw IllegalSubmissionTransition::because('submit', $this);
    }

    public function assignGrade(Submission $submission, float $score): Grade
    {
        throw IllegalSubmissionTransition::because('re-grade', $this);
    }
}
