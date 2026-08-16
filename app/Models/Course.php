<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Owned by Module 2. Module 1 reads this model but never writes to it
 * (EduSystem.md Section 2A).
 */
class Course extends Model
{
    use HasFactory;

    protected $fillable = [
        'instructor_id',
        'code',
        'title',
        'description',
    ];

    /**
     * Code and name together, the way a course is normally referred to.
     */
    public function label(): string
    {
        return $this->code.' '.$this->title;
    }

    public function instructor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'instructor_id');
    }

    /**
     * Students enrolled in this course.
     */
    public function students(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'course_student', 'course_id', 'student_id')
            ->withTimestamps();
    }

    /**
     * Learning paths this course forms a step of.
     */
    public function learningPaths(): BelongsToMany
    {
        return $this->belongsToMany(LearningPath::class, 'learning_path_course')
            ->withPivot('sequence')
            ->withTimestamps();
    }

    public function studentProgress(): HasMany
    {
        return $this->hasMany(StudentProgress::class);
    }

    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class);
    }

    /**
     * Module 2 -- lecture notes, tutorials and practicals.
     */
    public function materials(): HasMany
    {
        return $this->hasMany(CourseMaterial::class);
    }

    /**
     * Module 2 -- announcements addressed to this course.
     */
    public function announcements(): HasMany
    {
        return $this->hasMany(Announcement::class);
    }

    /**
     * Module 3 -- the course's single Q&A forum.
     */
    public function forum(): HasOne
    {
        return $this->hasOne(DiscussionForum::class);
    }

    /**
     * Module 4.
     */
    public function quizzes(): HasMany
    {
        return $this->hasMany(Quiz::class);
    }

    /**
     * Module 5.
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }

    /**
     * Is this student enrolled here? Used all over the access checks.
     */
    public function hasStudent(User $user): bool
    {
        return $this->students()->whereKey($user->id)->exists();
    }
}
