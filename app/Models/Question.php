<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Module 4. `type` selects the grading Strategy at run time.
 */
class Question extends Model
{
    use HasFactory;

    /**
     * The question types the Strategy pattern has an implementation for.
     */
    public const TYPE_MCQ = 'mcq';

    public const TYPE_TEXT = 'text';

    /**
     * Multiple correct answers, all of which must be selected.
     */
    public const TYPE_MULTI = 'multi';

    /**
     * A multiple-answer question is only meaningful with at least this many
     * correct options -- one would just be a single-choice question.
     */
    public const MIN_MULTI_ANSWERS = 2;

    protected $fillable = [
        'quiz_id',
        'type',
        'question_text',
    ];

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    /**
     * The choices for an MCQ, or the single accepted wording for a
     * fill-in-the-blank.
     */
    public function answers(): HasMany
    {
        return $this->hasMany(Answer::class);
    }

    public function correctAnswers(): HasMany
    {
        return $this->answers()->where('is_correct', true);
    }

    /**
     * Exactly how many options a student must tick.
     *
     * Derived from the number of correct options rather than stored in its own
     * column: the two could otherwise disagree, and a question claiming "choose
     * 3" while holding 2 correct answers would be unanswerable.
     */
    public function requiredSelections(): int
    {
        return $this->answers->where('is_correct', true)->count()
            ?: $this->correctAnswers()->count();
    }

    /**
     * The instruction shown above the options, so a student is never left
     * guessing how many to pick.
     */
    public function selectionInstruction(): string
    {
        return match ($this->type) {
            self::TYPE_MULTI => 'Select exactly '.$this->requiredSelections().' answers.',
            self::TYPE_MCQ => 'Select one answer.',
            default => 'Type your answer.',
        };
    }
}
