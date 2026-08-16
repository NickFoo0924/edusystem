<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An inbox item. Module 3's Observer produces these; Module 1 displays them
 * (EduSystem.md Section 2A).
 */
class Notification extends Model
{
    use HasFactory;

    /**
     * The table carries created_at only.
     */
    const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'type',
        'message',
        'link',
        'is_read',
    ];

    protected function casts(): array
    {
        return [
            'is_read' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
