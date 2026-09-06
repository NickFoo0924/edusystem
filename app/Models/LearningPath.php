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
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An ordered sequence of courses that earns a higher-tier pathway certificate
 * once every course in it is complete (EduSystem.md 1C).
 */
class LearningPath extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'certificate_template_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * The courses making up this path, in the order they should be taken.
     */
    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'learning_path_course')
            ->withPivot('sequence')
            ->withTimestamps()
            ->orderBy('learning_path_course.sequence');
    }

    /**
     * The template the pathway certificate is rendered from.
     */
    public function certificateTemplate(): BelongsTo
    {
        return $this->belongsTo(CertificateTemplate::class);
    }

    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class);
    }
}
