<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * How far one student has got in one course (EduSystem.md 1B).
 *
 * completion_percentage is a weighted composite of materials viewed, quizzes
 * passed and assignments submitted; the weights come from the `settings` table,
 * never from constants in code.
 */
class StudentProgress extends Model
{
    use HasFactory;

    /**
     * Named explicitly: Laravel would pluralise this to `student_progresses`.
     */
    protected $table = 'student_progress';

    protected $fillable = [
        'student_id',
        'course_id',
        'materials_viewed',
        'quizzes_passed',
        'assignments_submitted',
        'completion_percentage',
        'last_calculated_at',
    ];

    protected function casts(): array
    {
        return [
            'completion_percentage' => 'double',
            'last_calculated_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * The history behind the dashboard's progress-over-time chart.
     */
    public function snapshots(): HasMany
    {
        return $this->hasMany(ProgressSnapshot::class);
    }
}
