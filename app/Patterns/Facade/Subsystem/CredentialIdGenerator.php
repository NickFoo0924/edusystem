<?php

namespace App\Patterns\Facade\Subsystem;

use App\Models\Certificate;
use RuntimeException;

/**
 * SUBSYSTEM COMPONENT -- minting the human-readable credential ID.
 *
 * One of the five collaborators hidden behind the CredentialAuthority Facade.
 * A caller wanting a credential never touches this class; it asks the Facade
 * to issue a certificate and the Facade orchestrates this along with hashing,
 * rendering, progress and badges.
 *
 * Note for the report: uniqueness is guaranteed by the unique index on
 * `certificates.credential_id` plus the retry loop below -- NOT by there being
 * only one instance of this class. PHP shares no memory between concurrent
 * requests, so two simultaneous issuances were always two separate objects
 * even under the old Singleton. Nothing was lost by removing it.
 */
class CredentialIdGenerator
{
    /**
     * Credential ID format: LS-{YEAR}-{8 CHAR BASE32}, e.g. LS-2026-A7F3D9K2.
     */
    private const CREDENTIAL_PREFIX = 'LS';

    private const CREDENTIAL_RANDOM_LENGTH = 8;

    /**
     * Crockford Base32: the digits 0-9 and the letters A-Z with I, L, O and U
     * removed, so a credential ID read aloud or copied off a printed
     * certificate cannot be confused between 1/I/L or 0/O.
     */
    private const BASE32_ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    /**
     * How many times to retry if a generated ID happens to collide.
     */
    private const MAX_ID_ATTEMPTS = 10;

    /**
     * Mint a globally unique, human-readable credential ID.
     */
    public function generate(): string
    {
        for ($attempt = 0; $attempt < self::MAX_ID_ATTEMPTS; $attempt++) {
            $candidate = sprintf(
                '%s-%s-%s',
                self::CREDENTIAL_PREFIX,
                now()->year,
                $this->randomBase32(self::CREDENTIAL_RANDOM_LENGTH)
            );

            if (! Certificate::where('credential_id', $candidate)->exists()) {
                return $candidate;
            }
        }

        throw new RuntimeException('Could not mint a unique credential ID after '.self::MAX_ID_ATTEMPTS.' attempts.');
    }

    /**
     * A cryptographically random Crockford Base32 string.
     */
    private function randomBase32(int $length): string
    {
        $alphabetLastIndex = strlen(self::BASE32_ALPHABET) - 1;
        $output = '';

        for ($i = 0; $i < $length; $i++) {
            $output .= self::BASE32_ALPHABET[random_int(0, $alphabetLastIndex)];
        }

        return $output;
    }
}
