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

    /**
     * The five slots the CredentialAuthority fills at issuance, each with the
     * wording used to describe it and a sample value used to preview it.
     *
     * The tokens live here rather than being repeated across the screens, so
     * a placeholder is named in one place.
     */
    public const SLOTS = [
        '{{student_name}}' => ['label' => 'Student name', 'sample' => 'Serena Lim Sze Kee'],
        '{{course_title}}' => ['label' => 'Course or learning path', 'sample' => 'Integrative Programming'],
        '{{score}}' => ['label' => 'Final score', 'sample' => '92'],
        '{{issued_date}}' => ['label' => 'Date of issue', 'sample' => '16 August 2026'],
        '{{credential_id}}' => ['label' => 'Credential ID', 'sample' => 'LS-2026-A7F3D9K2'],
    ];

    /**
     * The body as it will read once issued, using sample values.
     *
     * Shown wherever a template is displayed rather than edited. The stored
     * text is a form with blanks in it, and printing those blanks on screen
     * exposes field names to no purpose -- a reader wants to know what the
     * certificate will say.
     *
     * This is a preview only. The real substitution belongs to the
     * CredentialAuthority, which is the sole issuer of a credential.
     */
    public function previewBody(): string
    {
        $tokens = array_keys(self::SLOTS);
        $samples = array_map(fn (array $slot) => $slot['sample'], array_values(self::SLOTS));

        return str_replace($tokens, $samples, $this->body_text);
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
