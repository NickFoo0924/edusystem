<?php

namespace App\Http\Controllers;

use App\Models\Certificate;
use App\Models\Course;
use App\Models\User;
use App\Patterns\Singleton\CredentialAuthority;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

/**
 * BUILD PRIORITY ITEM 7 -- the administrator's credential register
 * (EduSystem.md 1C and Section 7).
 *
 * Two rights meet here, and they are separate permission keys:
 *   certificate.revoke -- withdraw a credential with a stated reason
 *   certificate.issue  -- mint one by hand, outside the automatic flow
 *
 * Neither action is implemented in this controller. Both delegate to the
 * CredentialAuthority Singleton, which remains the only thing in the system
 * that may create or withdraw a credential. An administrator can never edit an
 * issued certificate's contents, only revoke it -- that is what keeps the
 * integrity hash meaningful (Section 7).
 */
class CertificateAdminController extends Controller
{
    private const PER_PAGE = 20;

    public function __construct(private CredentialAuthority $authority)
    {
    }

    /**
     * The register of every credential the system has issued.
     */
    public function index(Request $request): View
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
     * Reaching for the Singleton rather than writing a row means a manual
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
}
