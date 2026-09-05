<?php

namespace App\Support\Api;

/**
 * CONSUMES Module 1's getCredentialStatus service.
 *
 * Used by Module 5's analytics to report how many students in a cohort hold a
 * live credential, without Module 5 needing to understand integrity hashes or
 * revocation. It asks Module 1 whether a credential is currently valid and
 * takes the answer.
 */
class CredentialStatusClient extends ServiceClient
{
    protected function requestPrefix(): string
    {
        return 'CRED-REQ';
    }

    /**
     * Whether a credential is genuine, and nothing about its holder.
     *
     * detailFlag 1 is used on purpose. Module 5 is counting valid credentials,
     * so it has no business receiving names and marks it does not need.
     *
     * @return array<string, mixed>|null
     */
    public function status(string $credentialId): ?array
    {
        return $this->get('/credentials/verify', [
            'credentialId' => $credentialId,
            'detailFlag' => 1,
        ]);
    }

    /**
     * Status plus the holder's details, for a screen that displays them.
     *
     * @return array<string, mixed>|null
     */
    public function statusWithHolder(string $credentialId): ?array
    {
        return $this->get('/credentials/verify', [
            'credentialId' => $credentialId,
            'detailFlag' => 2,
        ]);
    }

    /**
     * Is this credential currently valid?
     *
     * Returns false when the service cannot be reached, which is the safe
     * answer: an unreachable authority must never be read as confirmation.
     */
    public function isValid(string $credentialId): bool
    {
        $data = $this->status($credentialId);

        return ($data['credentialStatus'] ?? null) === 'VALID';
    }
}
