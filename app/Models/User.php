<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    /**
     * Per-request cache behind permissions().
     *
     * @var \Illuminate\Database\Eloquent\Collection<int, Permission>|null
     */
    private $resolvedPermissions = null;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'school_email',
        'password',
        'role',
        'avatar_path',
        'bio',
        'phone',
        'show_phone',
        'is_active',
        'failed_login_attempts',
        'locked_until',
        'must_change_password',
        'last_login_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'show_phone' => 'boolean',
            'must_change_password' => 'boolean',
            'locked_until' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    /**
     * The address to publish on a contact card.
     *
     * The institutional one when it exists, otherwise the account address.
     * Never null, because "email is always shown" is the whole point of the
     * card -- a student must never land on it with no way to make contact.
     */
    public function contactEmail(): string
    {
        return filled($this->school_email) ? $this->school_email : $this->email;
    }

    /**
     * The phone number a student is allowed to see, or null.
     *
     * Both conditions must hold: a number exists, and its owner has chosen to
     * publish it. Anything reading a phone number for display must come through
     * here rather than touching $user->phone, so the opt-in cannot be bypassed
     * by a view that forgot to check.
     */
    public function publicPhone(): ?string
    {
        if (! $this->show_phone || blank($this->phone)) {
            return null;
        }

        return $this->phone;
    }

    /**
     * Does this user have a public contact card?
     *
     * Only people who teach. Students must never be listed this way -- Section
     * 7 is explicit that a student cannot browse another student's details.
     */
    public function hasPublicProfile(): bool
    {
        return $this->role === 'instructor';
    }

    /**
     * The single capital letter shown when there is no avatar image.
     *
     * The first letter of the name: "Foo Chong Xian" gives F, "Wong Siew Lam"
     * gives W. Falls back to the email if a name somehow starts with something
     * that is not a letter, so the circle is never empty.
     */
    public function avatarLetter(): string
    {
        $source = trim($this->name) !== '' ? trim($this->name) : $this->email;

        return Str::upper(Str::substr($source, 0, 1));
    }

    /**
     * The uploaded avatar, or null when the letter placeholder should be used.
     */
    public function avatarUrl(): ?string
    {
        if (blank($this->avatar_path)) {
            return null;
        }

        return Storage::disk('public')->url($this->avatar_path);
    }

    /**
     * A stable colour for the placeholder circle, derived from the name.
     *
     * Deterministic rather than random so a person keeps the same colour on
     * every page, which makes them recognisable at a glance in a list.
     *
     * @return array{0: string, 1: string}  background and text classes
     */
    public function avatarColour(): array
    {
        $palette = [
            ['bg-blue-100', 'text-blue-800'],
            ['bg-emerald-100', 'text-emerald-800'],
            ['bg-amber-100', 'text-amber-800'],
            ['bg-violet-100', 'text-violet-800'],
            ['bg-rose-100', 'text-rose-800'],
            ['bg-teal-100', 'text-teal-800'],
        ];

        return $palette[crc32($this->name) % count($palette)];
    }

    /**
     * Courses this user teaches. Instructors only.
     */
    public function coursesTeaching(): HasMany
    {
        return $this->hasMany(Course::class, 'instructor_id');
    }

    /**
     * Courses this user is enrolled in. Students only.
     */
    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'course_student', 'student_id', 'course_id')
            ->withTimestamps();
    }

    /**
     * Course invitations addressed to this student. A pending one is the only
     * way an uninvited course appears on their Courses page at all.
     */
    public function courseInvitations(): HasMany
    {
        return $this->hasMany(CourseInvitation::class, 'student_id');
    }

    /**
     * One progress record per course this student is enrolled in.
     */
    public function studentProgress(): HasMany
    {
        return $this->hasMany(StudentProgress::class, 'student_id');
    }

    /**
     * Credentials issued to this student by the CredentialAuthority.
     */
    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class, 'student_id');
    }

    /**
     * Badges this student has earned. The trophy cabinet renders these in
     * colour and every other active badge greyed out.
     */
    public function badges(): BelongsToMany
    {
        return $this->belongsToMany(Badge::class, 'badge_student', 'student_id', 'badge_id')
            ->withPivot('awarded_at');
    }

    /**
     * Module 3 -- forum activity.
     */
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function replies(): HasMany
    {
        return $this->hasMany(Reply::class);
    }

    /**
     * Module 5 -- coursework and quiz sittings.
     */
    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class, 'student_id');
    }

    public function quizAttempts(): HasMany
    {
        return $this->hasMany(QuizAttempt::class, 'student_id');
    }

    /**
     * Module 2 -- announcements this user has written.
     */
    public function announcements(): HasMany
    {
        return $this->hasMany(Announcement::class, 'author_id');
    }

    /**
     * Audit trail rows where this user was the actor.
     */
    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    /**
     * Invitations this administrator has issued.
     */
    public function invitationsSent(): HasMany
    {
        return $this->hasMany(Invitation::class, 'invited_by');
    }

    /**
     * This user's inbox.
     *
     * Deliberately overrides the relation of the same name from the Notifiable
     * trait: Module 1 owns a plain `notifications` table (Section 2A) rather
     * than Laravel's polymorphic database-notification table. Notifiable is
     * still needed for Breeze's password-reset mail, which uses the mail
     * channel and never touches this relation.
     */
    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    /**
     * Which notification types this user has opted into.
     */
    public function notificationPreferences(): HasMany
    {
        return $this->hasMany(NotificationPreference::class);
    }

    /**
     * Every permission granted to this user's role.
     *
     * Resolved from the database rather than hardcoded, so the admin permission
     * grid can retune access at runtime (EduSystem.md Section 7).
     * AppServiceProvider turns this into a Gate, which is why authorisation is
     * written $user->can('certificate.revoke') and never $user->role === 'admin'.
     *
     * Memoised because the Gate consults it on every single check.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Permission>
     */
    public function permissions()
    {
        return $this->resolvedPermissions ??= Permission::whereHas('permissionRoles', function ($query) {
            $query->where('role', $this->role);
        })->get();
    }
}
