<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Module 5.
 */
class Assignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'course_id',
        'title',
        'description',
        'due_date',
        'allow_late_submission',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'datetime',
            'allow_late_submission' => 'boolean',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class);
    }

    public function isOverdue(): bool
    {
        return $this->due_date->isPast();
    }

    /**
     * Is this assignment still taking work?
     *
     * Only an assignment that both refuses late work and is past its deadline
     * is closed. The default policy never closes.
     */
    public function isClosed(): bool
    {
        return ! $this->allow_late_submission && $this->isOverdue();
    }

    /**
     * Would work handed in right now be counted as late?
     */
    public function wouldBeLate(): bool
    {
        return $this->isOverdue();
    }

    /**
     * The policy in one line, for students and instructors alike.
     */
    public function latePolicyLabel(): string
    {
        return $this->allow_late_submission
            ? 'Late submissions are accepted and marked "Turned in late".'
            : 'This assignment closes at the deadline. Nothing is accepted afterwards.';
    }
}
