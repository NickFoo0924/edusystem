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
 * CONCRETE STRATEGY -- fill in the blank.
 *
 * Typed answers are never going to match character for character, so this
 * strategy normalises both sides and then measures similarity rather than
 * demanding equality:
 *
 *   exact after normalising  -> full marks
 *   >= 85% similar           -> full marks, treated as a typo
 *   >= 70% similar           -> half marks
 *   below that               -> wrong
 *
 * Similarity is PHP's built-in similar_text, which needs no extra package.
 */
class TextMatchGradingStrategy implements GradingStrategy
{
    private const TYPO_THRESHOLD = 85.0;

    private const PARTIAL_THRESHOLD = 70.0;

    public function grade(Question $question, ?string $response): GradedAnswer
    {
        if (blank($response)) {
            return GradedAnswer::incorrect('No answer given.');
        }

        $accepted = $question->answers()
            ->where('is_correct', true)
            ->pluck('answer_text')
            ->all();

        if ($accepted === []) {
            return GradedAnswer::partial(1.0, 'This question has no model answer set; full marks awarded.');
        }

        $given = $this->normalise($response);
        $best = 0.0;

        // Any one of the accepted wordings will do; keep the closest match.
        foreach ($accepted as $candidate) {
            $expected = $this->normalise($candidate);

            if ($given === $expected) {
                return GradedAnswer::correct('Exact match.');
            }

            similar_text($given, $expected, $percent);
            $best = max($best, $percent);
        }

        if ($best >= self::TYPO_THRESHOLD) {
            return new GradedAnswer(true, 1.0, 'Accepted — close enough to the model answer ('.round($best).'% similar).');
        }

        if ($best >= self::PARTIAL_THRESHOLD) {
            return GradedAnswer::partial(0.5, 'Partially correct ('.round($best).'% similar to the model answer).');
        }

        return GradedAnswer::incorrect('That does not match the expected answer.');
    }

    public function describe(): string
    {
        return 'Case- and spacing-insensitive similarity against the model answer, tolerating typos.';
    }

    /**
     * Lower-case, strip punctuation, collapse runs of whitespace.
     */
    private function normalise(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = preg_replace('/[^\p{L}\p{N}\s]/u', '', $value) ?? $value;

        return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
    }
}
