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
 * The student is still working. They may re-upload as often as they like, and
 * nothing can be marked yet.
 */
class DraftState implements SubmissionState
{
    public function name(): string
    {
        return 'draft';
    }

    public function label(): string
    {
        return 'a draft';
    }

    public function canUpdateFile(): bool
    {
        return true;
    }

    public function canSubmit(): bool
    {
        return true;
    }

    public function canAssignGrade(): bool
    {
        return false;
    }

    public function updateFile(Submission $submission, string $path): void
    {
        $submission->update(['file_path' => $path]);
    }

    public function submit(Submission $submission): void
    {
        $submission->update([
            'state' => (new SubmittedState())->name(),
            // Stamped here rather than derived from updated_at, because the
            // on-time badge rule compares this against the assignment due date.
            'submitted_at' => now(),
        ]);
    }

    public function assignGrade(Submission $submission, float $score): Grade
    {
        throw IllegalSubmissionTransition::because('assign a grade', $this);
    }
}
