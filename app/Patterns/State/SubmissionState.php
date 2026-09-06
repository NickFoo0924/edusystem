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
 * MODULE 5 DESIGN PATTERN -- STATE (Behavioural).
 *
 * A Submission behaves differently depending on where it is in its lifecycle,
 * and the State pattern puts that difference in the state object rather than in
 * a chain of if-statements spread across controllers.
 *
 * Draft     -- the student may re-upload freely; nothing may be graded yet.
 * Submitted -- locked from edits, waiting for the instructor.
 * Graded    -- final; neither the file nor the mark may change.
 *
 * Every transition and every permission question goes through the state object,
 * so a controller can never accidentally let a student edit work that has
 * already been marked (EduSystem.md Section 2).
 */
interface SubmissionState
{
    /**
     * The value stored in submissions.state for this state.
     */
    public function name(): string;

    /**
     * Human-readable label for the UI.
     */
    public function label(): string;

    /**
     * May the student replace the uploaded file?
     */
    public function canUpdateFile(): bool;

    /**
     * May the student hand the work in from here?
     */
    public function canSubmit(): bool;

    /**
     * May an instructor put a mark against it?
     */
    public function canAssignGrade(): bool;

    /**
     * Replace the uploaded file.
     *
     * @throws IllegalSubmissionTransition when this state forbids it
     */
    public function updateFile(Submission $submission, string $path): void;

    /**
     * Hand the work in, moving to the submitted state.
     *
     * @throws IllegalSubmissionTransition when this state forbids it
     */
    public function submit(Submission $submission): void;

    /**
     * Record the authoritative mark and move to the graded state.
     *
     * @throws IllegalSubmissionTransition when this state forbids it
     */
    public function assignGrade(Submission $submission, float $score): Grade;
}
