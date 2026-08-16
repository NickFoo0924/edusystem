<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Storage;

/**
 * A configurable award rule, not a hardcoded achievement (EduSystem.md 1D).
 *
 * The CredentialAuthority loads every active badge once per request and
 * evaluates the whole registry after any grade event.
 */
class Badge extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'icon_path',
        'tier',
        'criteria_type',
        'criteria_value',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Public URL of the uploaded icon, or null when this badge falls back to
     * the built-in medal for its tier.
     */
    public function iconUrl(): ?string
    {
        if (blank($this->icon_path)) {
            return null;
        }

        return Storage::disk('public')->url($this->icon_path);
    }

    /**
     * Students who have earned this badge.
     */
    public function students(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'badge_student', 'badge_id', 'student_id')
            ->withPivot('awarded_at');
    }
}
