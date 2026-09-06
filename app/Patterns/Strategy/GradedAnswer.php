<?php

/**
 * LearnSync -- Strategy pattern (behavioural)
 *
 * Module 4: Skill Assessment & Quiz
 *
 * @author Wong Siew Lam
 */

namespace App\Patterns\Strategy;

/**
 * The result of marking one answer.
 *
 * A tiny immutable value object so every strategy returns the same shape, and
 * partial credit is possible without changing any caller.
 */
final class GradedAnswer
{
    public function __construct(
        public readonly bool $isCorrect,
        /** Mark for this question, from 0.0 to 1.0. */
        public readonly float $score,
        /** Why it was marked that way, shown when reviewing an attempt. */
        public readonly string $explanation,
    ) {
    }

    public static function correct(string $explanation = 'Correct.'): self
    {
        return new self(true, 1.0, $explanation);
    }

    public static function incorrect(string $explanation = 'Incorrect.'): self
    {
        return new self(false, 0.0, $explanation);
    }

    public static function partial(float $score, string $explanation): self
    {
        return new self(false, max(0.0, min(1.0, $score)), $explanation);
    }
}
