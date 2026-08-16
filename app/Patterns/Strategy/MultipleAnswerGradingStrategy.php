<?php

namespace App\Patterns\Strategy;

use App\Models\Question;

/**
 * CONCRETE STRATEGY -- multiple correct answers.
 *
 * The student must tick every correct option and no incorrect one. The response
 * arrives as a comma-separated list of Answer ids.
 *
 * A third algorithm again, genuinely unlike the other two: the single-choice
 * strategy tests membership of one id, the text strategy measures string
 * similarity, and this one compares two sets. Adding it needed no change to the
 * controller that grades attempts -- it asks the resolver for a strategy and
 * calls grade(). That is what the Strategy pattern is for.
 */
class MultipleAnswerGradingStrategy implements GradingStrategy
{
    public function grade(Question $question, ?string $response): GradedAnswer
    {
        $required = $question->answers->where('is_correct', true)->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->sort()
            ->values();

        if ($required->isEmpty()) {
            // The instructor's oversight, not the student's problem.
            return GradedAnswer::partial(1.0, 'This question has no correct options set; full marks awarded.');
        }

        $chosen = collect(explode(',', (string) $response))
            ->map(fn ($id) => trim($id))
            ->filter()
            ->unique()
            ->values();

        if ($chosen->isEmpty()) {
            return GradedAnswer::incorrect('No options selected.');
        }

        $expected = $required->count();

        // The form enforces the count, so a mismatch means it was bypassed.
        // Grade it honestly rather than rejecting it outright.
        if ($chosen->count() !== $expected) {
            return GradedAnswer::incorrect(
                "Select exactly {$expected} answers — you selected {$chosen->count()}."
            );
        }

        $correctlyChosen = $chosen->intersect($required)->count();

        if ($correctlyChosen === $expected) {
            return GradedAnswer::correct('All '.$expected.' correct options selected.');
        }

        /*
         * Partial credit in proportion to how many were right. Getting three of
         * four is genuinely better than getting none, and all-or-nothing would
         * make a four-answer question far harsher than four separate ones.
         */
        return GradedAnswer::partial(
            $correctlyChosen / $expected,
            "{$correctlyChosen} of {$expected} correct options selected."
        );
    }

    public function describe(): string
    {
        return 'Set comparison: every correct option must be selected, with partial credit for a near miss.';
    }
}
