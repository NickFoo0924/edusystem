<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A verifiable digital credential (EduSystem.md 1C).
 *
 * Only the CredentialAuthority Facade may create, revoke or verify one --
 * see app/Patterns/Facade/CredentialAuthority.php. Nothing else in the
 * system mints a credential_id.
 */
class Certificate extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'course_id',
        'learning_path_id',
        'certificate_template_id',
        'credential_id',
        'final_score',
        'integrity_hash',
        'pdf_path',
        'issued_at',
        'expires_at',
        'revoked_at',
        'revocation_reason',
    ];

    protected function casts(): array
    {
        return [
            'final_score' => 'double',
            'issued_at' => 'datetime',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /**
     * Narrow a credential list down for the administrator's screen.
     *
     * Status is derived rather than stored, so it is expressed here as query
     * conditions on revoked_at / expires_at. The authoritative status for
     * public verification is still whatever CredentialAuthority::verify()
     * returns -- only that method also checks the integrity hash.
     *
     * @param  array<string, mixed>  $filters
     */
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query
            ->when(filled($filters['student_id'] ?? null),
                fn (Builder $q) => $q->where('student_id', $filters['student_id']))
            ->when(filled($filters['credential_id'] ?? null),
                fn (Builder $q) => $q->where('credential_id', 'like', '%'.$filters['credential_id'].'%'))
            ->when(($filters['status'] ?? null) === 'revoked',
                fn (Builder $q) => $q->whereNotNull('revoked_at'))
            ->when(($filters['status'] ?? null) === 'expired',
                fn (Builder $q) => $q->whereNull('revoked_at')
                    ->whereNotNull('expires_at')
                    ->where('expires_at', '<', now()))
            ->when(($filters['status'] ?? null) === 'valid',
                fn (Builder $q) => $q->whereNull('revoked_at')
                    ->where(fn (Builder $inner) => $inner->whereNull('expires_at')
                        ->orWhere('expires_at', '>=', now())));
    }

    /**
     * The holder of this credential.
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    /**
     * Set on a course certificate, null on a pathway certificate.
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * Set on a pathway certificate, null on a course certificate.
     */
    public function learningPath(): BelongsTo
    {
        return $this->belongsTo(LearningPath::class);
    }

    public function certificateTemplate(): BelongsTo
    {
        return $this->belongsTo(CertificateTemplate::class);
    }
}
