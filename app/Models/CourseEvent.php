<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Module 2 -- something scheduled: a class, an online meeting, a briefing.
 *
 * A null course means an institution-wide event from an administrator, the
 * same convention Announcement uses.
 */
class CourseEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'course_id',
        'created_by',
        'title',
        'description',
        'type',
        'location',
        'meeting_url',
        'starts_at',
        'ends_at',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    /**
     * The kinds an instructor can schedule, and how each is labelled.
     */
    public const TYPES = [
        'class' => 'Class',
        'meeting' => 'Online meeting',
        'other' => 'Other',
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isGlobal(): bool
    {
        return $this->course_id === null;
    }

    /**
     * Events falling inside a window, by start time.
     */
    public function scopeBetween(Builder $query, $from, $to): Builder
    {
        return $query->whereBetween('starts_at', [$from, $to]);
    }

    /**
     * Events this user is entitled to see: global ones, their own courses',
     * and -- for an administrator -- everything.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->where(function (Builder $q) use ($user) {
            $q->whereNull('course_id');

            if ($user->can('analytics.view_system')) {
                $q->orWhereNotNull('course_id');

                return;
            }

            if ($user->can('course.enroll')) {
                $q->orWhereIn('course_id', $user->courses()->pluck('courses.id'));
            }

            if ($user->can('course.create')) {
                $q->orWhereIn('course_id', $user->coursesTeaching()->pluck('id'));
            }
        });
    }
}
