<?php

namespace App\Http\Controllers;

use App\Models\Certificate;
use App\Patterns\Singleton\CredentialAuthority;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The holder's view of their own credentials.
 *
 * Authorisation is by permission key, never by role (EduSystem.md Section 5).
 * The keys checked here are seeded rows resolved through the Gate registered in
 * AppServiceProvider.
 *
 * Issuance and revocation are absent by design: only the CredentialAuthority
 * mints a credential, so there is no store() or update() for a student to reach.
 */
class CertificateController extends Controller
{
    public function __construct(private CredentialAuthority $authority)
    {
    }

    /**
     * List the certificates belonging to the signed-in student.
     */
    public function index(Request $request): View
    {
        abort_unless($request->user()->can('certificate.view_own'), 403);

        $certificates = Certificate::with(['course', 'learningPath'])
            ->where('student_id', $request->user()->id)
            ->orderByDesc('issued_at')
            ->get();

        return view('certificates.index', compact('certificates'));
    }

    /**
     * Show one certificate, with its public verification link.
     */
    public function show(Request $request, Certificate $certificate): View
    {
        $this->authoriseHolder($request, $certificate);

        $certificate->load(['course', 'learningPath', 'student']);

        return view('certificates.show', [
            'certificate' => $certificate,
            'status' => $this->authority->verify($certificate->credential_id)['status'],
            'verificationUrl' => $this->authority->verificationUrl($certificate->credential_id),
        ]);
    }

    /**
     * Stream the stored PDF to its holder.
     *
     * A revoked credential cannot be downloaded (EduSystem.md 1C).
     */
    public function download(Request $request, Certificate $certificate): StreamedResponse
    {
        $this->authoriseHolder($request, $certificate);

        abort_if($certificate->revoked_at !== null, 403, 'This credential has been revoked.');
        abort_unless(Storage::disk('local')->exists($certificate->pdf_path), 404);

        return Storage::disk('local')->download(
            $certificate->pdf_path,
            $certificate->credential_id.'.pdf'
        );
    }

    /**
     * A student may only reach their own credentials. Section 7 is explicit
     * that one student cannot view another student's certificate list.
     */
    private function authoriseHolder(Request $request, Certificate $certificate): void
    {
        abort_unless($request->user()->can('certificate.view_own'), 403);
        abort_unless($certificate->student_id === $request->user()->id, 403);
    }
}
