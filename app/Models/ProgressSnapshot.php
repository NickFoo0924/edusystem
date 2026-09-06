<?php

/**
 * LearnSync -- Eloquent model
 *
 * Module 5: Academic Progress Analytics
 *
 * @author Ong Kwong Wei
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A point on the student's progress line chart, written every time the
 * CredentialAuthority recalculates progress (EduSystem.md 1B).
 */
class ProgressSnapshot extends Model
{
    use HasFactory;

    /**
     * The table records captured_at instead of Laravel's timestamp pair.
     */
    public $timestamps = false;

    protected $fillable = [
        'student_progress_id',
        'completion_percentage',
        'captured_at',
    ];

    protected function casts(): array
    {
        return [
            'completion_percentage' => 'double',
            'captured_at' => 'datetime',
        ];
    }

    public function studentProgress(): BelongsTo
    {
        return $this->belongsTo(StudentProgress::class);
    }
}
