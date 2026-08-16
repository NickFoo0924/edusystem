<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Module 3 -- the SUBJECT of the Observer pattern.
 *
 * Saving a Post notifies SystemNotificationObserver, which writes the alert
 * rows. The model itself knows nothing about notifications, which is the point
 * of the pattern.
 */
class Post extends Model
{
    use HasFactory;

    protected $fillable = [
        'forum_id',
        'user_id',
        'content',
    ];

    public function forum(): BelongsTo
    {
        return $this->belongsTo(DiscussionForum::class, 'forum_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(Reply::class);
    }
}
