<?php

/**
 * LearnSync -- Eloquent model
 *
 * Module 1: Identity, Access & Digital Credentialing
 *
 * @author Serena Lim Sze Kee
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * [STRETCH] Previous password hashes, so one cannot be reused (EduSystem.md 1A).
 */
class PasswordHistory extends Model
{
    use HasFactory;

    /**
     * How many previous passwords are barred from reuse.
     */
    public const REMEMBERED = 3;

    const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'password_hash',
    ];

    protected $hidden = [
        'password_hash',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The hashes a new password must not match.
     *
     * @return Collection<int, self>
     */
    public static function recentFor(User $user): Collection
    {
        return self::where('user_id', $user->id)
            ->latest('id')
            ->limit(self::REMEMBERED)
            ->get();
    }

    /**
     * Record the user's current password before it is replaced, and prune
     * anything older than the remembered window so the table cannot grow
     * without bound.
     */
    public static function remember(User $user): void
    {
        self::create([
            'user_id' => $user->id,
            'password_hash' => $user->password,
        ]);

        $keep = self::where('user_id', $user->id)
            ->latest('id')
            ->limit(self::REMEMBERED)
            ->pluck('id');

        self::where('user_id', $user->id)->whereNotIn('id', $keep)->delete();
    }
}
