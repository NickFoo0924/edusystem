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
 * CONCRETE STRATEGY -- multiple choice.
 *
 * The response is the id of the chosen Answer row. Marking is an exact
 * membership test against the options flagged correct.
 */
class MCQGradingStrategy implements GradingStrategy
{
    public function grade(Question $question, ?string $response): GradedAnswer
    {
        if (blank($response)) {
            return GradedAnswer::incorrect('No option chosen.');
        }

        $correctIds = $question->answers()
            ->where('is_correct', true)
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->all();

        if ($correctIds === []) {
            // A question with no correct option is the instructor's mistake,
            // not the student's; do not penalise them for it.
            return GradedAnswer::partial(1.0, 'This question has no correct option set; full marks awarded.');
        }

        return in_array($response, $correctIds, true)
            ? GradedAnswer::correct()
            : GradedAnswer::incorrect('That is not the correct option.');
    }

    public function describe(): string
    {
        return 'Exact match against the option marked correct.';
    }
}
