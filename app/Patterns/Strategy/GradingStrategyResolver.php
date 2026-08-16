<?php

namespace App\Patterns\Strategy;

use App\Models\Question;
use InvalidArgumentException;

/**
 * Picks the strategy for a question type.
 *
 * This is the one place that maps a type string to an algorithm, which is what
 * keeps the swap dynamic: the controller calls resolver->for($question) and has
 * no idea which class comes back.
 *
 * A plain helper, not a second design pattern -- Module 4's one GoF pattern is
 * the Strategy (EduSystem.md Section 2).
 */
class GradingStrategyResolver
{
    /**
     * @var array<string, class-string<GradingStrategy>>
     */
    private const STRATEGIES = [
        Question::TYPE_MCQ => MCQGradingStrategy::class,
        Question::TYPE_TEXT => TextMatchGradingStrategy::class,
    ];

    public function for(Question $question): GradingStrategy
    {
        $class = self::STRATEGIES[$question->type] ?? null;

        if ($class === null) {
            throw new InvalidArgumentException("No grading strategy is registered for question type \"{$question->type}\".");
        }

        return new $class();
    }

    /**
     * The question types the engine can mark, for building form dropdowns.
     *
     * @return array<string, string>
     */
    public static function availableTypes(): array
    {
        return [
            Question::TYPE_MCQ => 'Multiple choice',
            Question::TYPE_TEXT => 'Fill in the blank',
        ];
    }
}
