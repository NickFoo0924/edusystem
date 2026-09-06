<?php

/**
 * LearnSync -- Strategy pattern (behavioural)
 *
 * Module 4: Skill Assessment & Quiz
 *
 * @author Wong Siew Lam
 */

namespace App\Patterns\Strategy;

use App\Models\Question;

/**
 * MODULE 4 DESIGN PATTERN -- STRATEGY (Behavioural). The strategy interface.
 *
 * Different question types are marked by genuinely different algorithms: an MCQ
 * is an exact match against the chosen option, while a fill-in-the-blank has to
 * tolerate case, spacing and small typing errors.
 *
 * Putting each algorithm behind this interface means the controller never grows
 * a switch statement over question types. It asks the resolver for a strategy
 * and calls grade() -- and a new question type later means a new class, not an
 * edit to existing code (EduSystem.md Section 2).
 */
interface GradingStrategy
{
    /**
     * Mark one response.
     *
     * @param  Question  $question  the question being answered
     * @param  string|null  $response  what the student gave
     * @return GradedAnswer the verdict and the mark out of 1
     */
    public function grade(Question $question, ?string $response): GradedAnswer;

    /**
     * A short description of how this strategy marks, shown to instructors.
     */
    public function describe(): string;
}
