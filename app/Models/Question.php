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
}
