<?php

/**
 * LearnSync -- Eloquent model
 *
 * Module 1: Identity, Access & Digital Credentialing
 *
 * @author Serena Lim Sze Kee
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Hash;

/**
 * [STRETCH] A one-time code emailed as a second factor (EduSystem.md 1A).
 *
 * Six digits, five-minute expiry, stored hashed.
 */
class OtpCode extends Model
{
    use HasFactory;

    public const LIFETIME_MINUTES = 5;

    protected $fillable = [
        'user_id',
        'code_hash',
        'expires_at',
        'consumed_at',
    ];

    protected $hidden = [
        'code_hash',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Mint a code for a user and return the plain digits, which are emailed and
     * then forgotten -- only the hash is kept.
     *
     * Any earlier unused code is discarded so a user never holds two live codes.
     */
    public static function issueFor(User $user): string
    {
        self::where('user_id', $user->id)->whereNull('consumed_at')->delete();

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        self::create([
            'user_id' => $user->id,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(self::LIFETIME_MINUTES),
        ]);

        return $code;
    }

    /**
     * Check a submitted code and burn it. Returns false for an unknown, expired
     * or already-used code.
     */
    public static function consume(User $user, string $submitted): bool
    {
        $candidate = self::where('user_id', $user->id)
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();

        if ($candidate === null || ! Hash::check($submitted, $candidate->code_hash)) {
            return false;
        }

        $candidate->update(['consumed_at' => now()]);

        return true;
    }
}
