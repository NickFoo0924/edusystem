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
    /**
     * The unlock condition as a sentence.
     *
     * The admin screens were printing the raw criteria_type -- a reader saw
     * "on_time_submissions >= 5" and had to translate it themselves. The
     * column stays as it is, because the badge engine matches on those keys;
     * this is only how the rule is worded for a person.
     */
    public function criteriaDescription(): string
    {
        $value = $this->criteria_value;
        $times = $value === 1 ? 'once' : $value.' times';

        return match ($this->criteria_type) {
            'course_completion' => 'Complete '.$value.' '.($value === 1 ? 'course' : 'courses'),
            'path_completion' => 'Complete '.$value.' learning '.($value === 1 ? 'path' : 'paths'),
            'quiz_score' => 'Score '.$value.'% or higher on any quiz',
            'on_time_submissions' => 'Submit '.$value.' '.($value === 1 ? 'assignment' : 'assignments').' on time',
            'first_forum_post' => 'Post in a course forum for the first time',
            'login_streak' => 'Log in on '.$value.' consecutive days',
            default => ucfirst(str_replace('_', ' ', $this->criteria_type)).' — '.$times,
        };
    }

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'badge_student', 'badge_id', 'student_id')
            ->withPivot('awarded_at');
    }
}
