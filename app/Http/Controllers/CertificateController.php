<?php

namespace App\Http\Controllers;

use App\Models\Certificate;
use App\Models\Course;
use App\Models\User;
use App\Patterns\Facade\CredentialAuthority;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use RuntimeException;
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
    private const PER_PAGE = 20;

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

    /*
     * ---------------------------------------------------------------
     * THE ADMINISTRATOR'S REGISTER (EduSystem.md 1C, Section 7)
     *
     * Two separate permission keys meet here: certificate.issue mints one
     * by hand, certificate.revoke withdraws one with a stated reason.
     * Neither is implemented here -- both delegate to the
     * CredentialAuthority, which remains the only thing in the system
     * that may create or withdraw a credential, so a manual issuance is
     * identical to an automatic one. An administrator can never edit an
     * issued certificate's contents, only revoke it; that is what keeps
     * the integrity hash meaningful.
     * ---------------------------------------------------------------
     */

    /**
     * The register of every credential the system has issued.
     */
    public function adminIndex(Request $request): View
    {
        // Readable by either right: an administrator who may issue needs the
        // register to work from just as much as one who may revoke.
        abort_unless(
            $request->user()->can('certificate.revoke') || $request->user()->can('certificate.issue'),
            403
        );

        $filters = [
            'status' => $request->query('status'),
            'student_id' => $request->query('student_id'),
            'credential_id' => $request->query('credential_id'),
        ];

        $certificates = Certificate::with(['student', 'course', 'learningPath'])
            ->filter($filters)
            ->orderByDesc('issued_at')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('admin_certificates.index', [
            'certificates' => $certificates,
            'filters' => $filters,
            'students' => User::where('role', 'student')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * Form for issuing a credential by hand.
     */
    public function create(Request $request): View
    {
        abort_unless($request->user()->can('certificate.issue'), 403);

        return view('admin_certificates.create', [
            'students' => User::where('role', 'student')->orderBy('name')->get(),
            'courses' => Course::with('instructor')->orderBy('title')->get(),
        ]);
    }

    /**
     * Mint a credential manually.
     *
     * Reaching for the Facade rather than writing a row means a manual
     * issuance is identical to an automatic one: same credential ID format,
     * same integrity hash, same PDF, same badge evaluation, same audit entry.
     */
    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('certificate.issue'), 403);

        $data = $request->validate([
            'student_id' => ['required', 'exists:users,id'],
            'course_id' => ['required', 'exists:courses,id'],
            'final_score' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        $student = User::findOrFail($data['student_id']);
        $course = Course::findOrFail($data['course_id']);

        if ($student->role !== 'student') {
            return back()->withInput()
                ->with('error', 'Certificates can only be issued to student accounts.');
        }

        try {
            $certificate = $this->authority->issueCertificate($student, $course, (float) $data['final_score']);
        } catch (RuntimeException $e) {
            // The authority refuses duplicates; surface its reason rather than
            // a 500, because it is a business rule and not a fault.
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.certificates.index')
            ->with('success', "Issued {$certificate->credential_id} to {$student->name}.");
    }

    /**
     * Withdraw a credential with a stated reason.
     */
    public function revoke(Request $request, Certificate $certificate): RedirectResponse
    {
        abort_unless($request->user()->can('certificate.revoke'), 403);

        $data = $request->validate([
            // A reason is mandatory: it is published on the public verification
            // page, so "revoked, no reason given" is not an acceptable outcome.
            'revocation_reason' => ['required', 'string', 'min:10', 'max:255'],
        ], [
            'revocation_reason.required' => 'A revocation reason is required — it is shown publicly.',
            'revocation_reason.min' => 'Give a fuller reason; it is shown publicly.',
        ]);

        try {
            $this->authority->revoke($certificate, $data['revocation_reason']);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.certificates.index')
            ->with('success', "{$certificate->credential_id} revoked. Public verification now reports REVOKED.");
    }

    /*
     * ---------------------------------------------------------------
     * THE PUBLIC FACE
     *
     * Unauthenticated on purpose: Section 7 gives Role 0 (Guest) the
     * right to check a credential's authenticity, which is the whole
     * point of a verifiable one. An employer scanning the QR code on a
     * printed certificate has no account here, so this method is
     * registered outside the auth middleware group.
     * ---------------------------------------------------------------
     */

    /**
     * Display the verification result for a credential ID.
     */
    public function verify(string $credentialId): View
    {
        $result = $this->authority->verify($credentialId);

        return view('certificates.verify', [
            'status' => $result['status'],
            'certificate' => $result['certificate'],
            'credentialId' => $credentialId,
        ]);
    }
}
