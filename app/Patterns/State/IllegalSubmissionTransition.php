<?php

namespace App\Patterns\State;

use RuntimeException;

/**
 * Thrown when something asks a Submission to do what its current state forbids
 * -- editing work that is already submitted, or grading a draft.
 *
 * A dedicated exception type so controllers can turn it into a clear message
 * for the user instead of a 500.
 */
class IllegalSubmissionTransition extends RuntimeException
{
    public static function because(string $action, SubmissionState $state): self
    {
        return new self("Cannot {$action}: this submission is {$state->label()}.");
    }
}
