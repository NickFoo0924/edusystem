<?php

namespace App\Models;

use App\Patterns\State\DraftState;
use App\Patterns\State\GradedState;
use App\Patterns\State\SubmissionState;
use App\Patterns\State\SubmittedState;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Module 5 -- the CONTEXT of the State pattern.
 *
 * The model holds the data and delegates every lifecycle decision to its state
 * object. Nothing outside app/Patterns/State decides whether an edit or a grade
 * is allowed.
 */
class Submission extends Model
{
    use HasFactory;

    /**
     * The state column's value mapped to the class that implements it.
     */
    private const STATES = [
        'draft' => DraftState::class,
        'submitted' => SubmittedState::class,
        'graded' => GradedState::class,
    ];

    protected $fillable = [
        'assignment_id',
        'student_id',
        'file_path',
        'state',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
        ];
    }

    /**
     * The state object for this submission's current state.
     *
     * Unknown values fall back to draft, so a hand-edited database row can
     * never leave a submission with no behaviour at all.
     */
    public function state(): SubmissionState
    {
        $class = self::STATES[$this->state] ?? DraftState::class;

        return new $class();
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function grade(): HasOne
    {
        return $this->hasOne(Grade::class);
    }

    /**
     * Was this handed in before the deadline? Feeds the on_time_submissions
     * badge rule.
     */
    public function wasOnTime(): bool
    {
        if ($this->submitted_at === null) {
            return false;
        }

        return $this->submitted_at->lessThanOrEqualTo($this->assignment->due_date);
    }
}
