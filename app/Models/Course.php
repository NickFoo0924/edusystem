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
        'class_code',
        'title',
        'description',
    ];

    /**
     * Characters a join code may contain. 0/O and 1/l/I are left out because
     * these codes get read off a slide and typed by hand, and a code that
     * cannot be transcribed is a support request rather than a shortcut.
     */
    private const CODE_ALPHABET = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    /**
     * Every course gets a join code the moment it exists, so no creation path
     * -- controller, seeder or factory -- has to remember to supply one, and
     * the NOT NULL column can never be the thing that fails.
     */
    protected static function booted(): void
    {
        static::creating(function (Course $course) {
            $course->class_code ??= static::generateClassCode();
        });
    }

    /**
     * A fresh join code, checked against the table so the unique index is never
     * the thing that discovers a collision.
     */
    public static function generateClassCode(): string
    {
        do {
            $code = '';

            for ($i = 0; $i < 6; $i++) {
                $code .= self::CODE_ALPHABET[random_int(0, strlen(self::CODE_ALPHABET) - 1)];
            }
        } while (static::where('class_code', $code)->exists());

        return $code;
    }

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
     * Instructor invitations issued for this course, accepted or not.
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(CourseInvitation::class);
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
