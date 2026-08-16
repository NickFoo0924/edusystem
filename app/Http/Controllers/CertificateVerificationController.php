<?php

namespace App\Http\Controllers;

use App\Patterns\Singleton\CredentialAuthority;
use Illuminate\View\View;

/**
 * The public face of LearnSync's credentialing.
 *
 * This controller is deliberately unauthenticated: EduSystem.md Section 7 gives
 * Role 0 (Guest) the right to check a certificate's authenticity, which is the
 * whole point of a verifiable credential. An employer scanning the QR code on a
 * printed certificate has no account here.
 *
 * All the decision-making lives in the CredentialAuthority Singleton, which is
 * injected by the container. The controller only chooses a view.
 */
class CertificateVerificationController extends Controller
{
    public function __construct(private CredentialAuthority $authority)
    {
    }

    /**
     * Display the verification result for a credential ID.
     */
    public function show(string $credentialId): View
    {
        $result = $this->authority->verify($credentialId);

        return view('certificates.verify', [
            'status' => $result['status'],
            'certificate' => $result['certificate'],
            'credentialId' => $credentialId,
        ]);
    }
}
