<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A reusable certificate design. Administrators own these; instructors cannot
 * create them (EduSystem.md Section 7).
 */
class CertificateTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'background_path',
        'signature_path',
        'body_text',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class);
    }

    public function learningPaths(): HasMany
    {
        return $this->hasMany(LearningPath::class);
    }
}
