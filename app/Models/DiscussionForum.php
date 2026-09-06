<?php

/**
 * LearnSync -- Eloquent model
 *
 * Module 3: Student Forum & Notifications
 *
 * @author Ong Shun Yan
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Module 3. Exactly one forum per course.
 */
class DiscussionForum extends Model
{
    use HasFactory;

    protected $fillable = [
        'course_id',
        'title',
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class, 'forum_id');
    }
}
