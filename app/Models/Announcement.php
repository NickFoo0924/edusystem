<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public function isGlobal(): bool
    {
        return $this->course_id === null;
    }
}
