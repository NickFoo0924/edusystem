<?php

namespace App\Models;

use App\Support\GradeScale;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Module 5 -- the authoritative mark.
 *
 * Module 5 is the only writer; Module 1 reads these as input to progress and
 * credential decisions and never writes one (EduSystem.md Section 2A).
 */
class Grade extends Model
{
    use HasFactory;

    protected $fillable = [
        'submission_id',
        'quiz_attempt_id',
        'calculated_score',
    ];

    protected function casts(): array
    {
        return [
            'calculated_score' => 'double',
        ];
    }

    /**
     * The letter for this mark, derived from the scale rather than stored.
     */
    public function letter(): string
    {
        return GradeScale::letterFor($this->calculated_score);
    }

    public function gradePoint(): float
    {
        return GradeScale::pointFor($this->calculated_score);
    }

    public function isPass(): bool
    {
        return GradeScale::isPass($this->calculated_score);
    }

    /**
     * The mark as it is normally written: "88% (A)".
     */
    public function display(): string
    {
        $mark = rtrim(rtrim(number_format($this->calculated_score, 2), '0'), '.');

        return $mark.'% ('.$this->letter().')';
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }

    public function quizAttempt(): BelongsTo
    {
        return $this->belongsTo(QuizAttempt::class);
    }

    /**
     * The course this mark belongs to, whichever route it came in by. Used by
     * the CredentialAuthority to know which course to recalculate.
     */
    public function course(): ?Course
    {
        return $this->submission?->assignment?->course
            ?? $this->quizAttempt?->quiz?->course;
    }

    /**
     * The student who earned it.
     */
    public function student(): ?User
    {
        return $this->submission?->student ?? $this->quizAttempt?->student;
    }
}
