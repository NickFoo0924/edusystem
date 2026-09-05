<?php

namespace App\Patterns\Facade\Subsystem;

use App\Models\Certificate;

/**
 * SUBSYSTEM COMPONENT -- the tamper-evidence seal on a credential.
 *
 * One of the five collaborators hidden behind the CredentialAuthority Facade.
 * Computes the hash at issuance and recomputes it at verification, which is
 * what lets the public verification page report TAMPERED when somebody edits
 * the row directly in the database.
 */
class IntegrityHasher
{
    /**
     * SHA-256(student_id | course_id | score | issued_at | credential_id).
     */
    public function hash(
        int $studentId,
        ?int $courseId,
        float $score,
        string $issuedAt,
        string $credentialId
    ): string {
        return hash('sha256', implode('|', [
            $studentId,
            $courseId ?? '',
            $score,
            $issuedAt,
            $credentialId,
        ]));
    }

    /**
     * Recompute the stored hash and compare, proving the row has not been
     * edited directly in the database since issuance.
     *
     * Note for the report: revoked_at is deliberately not part of the hash,
     * because the spec fixes the formula at
     * SHA-256(student_id|course_id|score|issued_at|credential_id) and revocation
     * must remain possible after issuance without invalidating the hash.
     */
    public function matches(Certificate $certificate): bool
    {
        $recomputed = $this->hash(
            $certificate->student_id,
            $certificate->course_id,
            $certificate->final_score,
            $certificate->issued_at->format('Y-m-d H:i:s'),
            $certificate->credential_id
        );

        return hash_equals($certificate->integrity_hash, $recomputed);
    }
}
