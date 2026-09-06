<?php

/**
 * LearnSync -- Eloquent model
 *
 * Module 2: Academic Resources Repository
 *
 * @author Foo Chong Xian
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Module 2 -- one message in the conversation under an announcement.
 *
 * Observed by Module 3's SystemNotificationObserver, which is what turns a
 * question here into an inbox entry for whoever posted the notice. This model
 * knows nothing about that (EduSystem.md Section 2A).
 */
class AnnouncementComment extends Model
{
    use HasFactory;

    protected $fillable = [
        'announcement_id',
        'user_id',
        'body',
    ];

    public function announcement(): BelongsTo
    {
        return $this->belongsTo(Announcement::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
