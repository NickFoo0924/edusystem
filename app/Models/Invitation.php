<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A tokenised registration link. Replaces Breeze's open registration route
 * entirely (EduSystem.md 1A).
 */
class Invitation extends Model
{
    use HasFactory;

    protected $fillable = [
        'email',
        'role',
        'token',
        'invited_by',
        'expires_at',
        'accepted_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    /**
     * The administrator who issued this invitation.
     */
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    /**
     * Where this invitation is up to: accepted, expired or still pending.
     */
    public function status(): string
    {
        if ($this->accepted_at !== null) {
            return 'accepted';
        }

        if ($this->expires_at->isPast()) {
            return 'expired';
        }

        return 'pending';
    }

    /**
     * The tokenised link the recipient must follow to register.
     */
    public function registrationUrl(): string
    {
        return route('register.invited', ['token' => $this->token]);
    }
}
