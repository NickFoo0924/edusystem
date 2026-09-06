<?php

/**
 * LearnSync -- Eloquent model
 *
 * Module 1: Identity, Access & Digital Credentialing
 *
 * @author Serena Lim Sze Kee
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One immutable audit row. Written on login, logout, permission change,
 * certificate issue, certificate revoke and invitation activity
 * (EduSystem.md 1A).
 */
class ActivityLog extends Model
{
    use HasFactory;

    /**
     * The table carries created_at only -- an audit row is never updated.
     */
    const UPDATED_AT = null;

    /**
     * Stand-ins for the request fields when an action happens outside HTTP,
     * such as from a seeder or `php artisan tinker`.
     */
    private const CONSOLE_IP = 'console';

    private const CONSOLE_AGENT = 'artisan';

    protected $fillable = [
        'user_id',
        'action',
        'target_type',
        'target_id',
        'ip_address',
        'user_agent',
    ];

    /**
     * Write an audit row.
     *
     * This is the single entry point for the whole audit trail, so every call
     * site records the same fields the same way. It is deliberately a plain
     * helper and not a design pattern: Module 1's one GoF pattern is the
     * CredentialAuthority Facade (EduSystem.md Section 2).
     *
     * @param  string  $action  dotted action key, e.g. "certificate.revoked"
     * @param  Model|null  $target  the record acted upon, if any
     * @param  User|null  $actor  defaults to the authenticated user
     */
    public static function record(string $action, ?Model $target = null, ?User $actor = null): self
    {
        $actor ??= auth()->user();
        $request = request();

        return self::create([
            'user_id' => $actor?->id,
            'action' => $action,
            'target_type' => $target !== null ? $target::class : null,
            'target_id' => $target?->getKey(),
            // runningInConsole() covers seeders and tinker, where there is no
            // real client to attribute the action to.
            'ip_address' => app()->runningInConsole()
                ? self::CONSOLE_IP
                : (string) ($request->ip() ?? self::CONSOLE_IP),
            'user_agent' => app()->runningInConsole()
                ? self::CONSOLE_AGENT
                : substr((string) ($request->userAgent() ?? self::CONSOLE_AGENT), 0, 255),
        ]);
    }

    /**
     * The user who performed the action. Null for events with no authenticated
     * actor, such as a failed login against an unknown address.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Just the class name of the target, for display: "Certificate" rather than
     * "App\Models\Certificate".
     */
    public function targetLabel(): ?string
    {
        if ($this->target_type === null) {
            return null;
        }

        return class_basename($this->target_type).' #'.$this->target_id;
    }

    /**
     * Narrow the trail down. Every filter is optional and they compose, which
     * is what lets the same query serve both the screen and the CSV export.
     *
     * @param  array<string, mixed>  $filters
     */
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query
            ->when(filled($filters['action'] ?? null), fn (Builder $q) => $q->where('action', $filters['action']))
            ->when(filled($filters['user_id'] ?? null), fn (Builder $q) => $q->where('user_id', $filters['user_id']))
            ->when(filled($filters['from'] ?? null), fn (Builder $q) => $q->whereDate('created_at', '>=', $filters['from']))
            ->when(filled($filters['to'] ?? null), fn (Builder $q) => $q->whereDate('created_at', '<=', $filters['to']));
    }
}
