<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Module 2. A null course means a global announcement from an administrator.
 */
class Announcement extends Model
{
    use HasFactory;

    protected $fillable = [
        'course_id',
        'author_id',
        'content',
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * The conversation underneath, oldest first -- it reads as a chat, not as
     * a feed.
     */
    public function comments(): HasMany
    {
        return $this->hasMany(AnnouncementComment::class)->oldest();
    }

    public function isGlobal(): bool
    {
        return $this->course_id === null;
    }

    /**
     * May this user read, and therefore comment on, this announcement?
     *
     * The same rule the index query expresses, in a form a single announcement
     * can be tested against -- so commenting can never reach further than
     * reading does.
     */
    public function isVisibleTo(User $user): bool
    {
        if ($this->isGlobal()) {
            return true;
        }

        if ($user->can('analytics.view_system')) {
            return true;
        }

        $course = $this->course;

        if ($course === null) {
            return false;
        }

        return $course->instructor_id === $user->id
            || ($user->can('course.enroll') && $course->hasStudent($user));
    }
}
