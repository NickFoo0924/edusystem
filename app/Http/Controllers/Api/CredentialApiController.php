<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Patterns\Facade\CredentialAuthority;
use App\Support\Ifa;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * MODULE 1 (Serena Lim Sze Kee) exposes: getCredentialStatus.
 *
 * Consumed by Module 5's analytics, and by any outside party wanting to check
 * a certificate. This service is public, with no API key, because
 * EduSystem.md Section 7 gives an unauthenticated guest exactly one right:
 * confirming that a credential is genuine.
 *
 * Note how little this controller knows. It validates the request, asks the
 * CredentialAuthority Facade one question, and shapes the answer to the IFA.
 * Hashing, PDF rendering and badge rules are all behind the Facade, so adding
 * a web service on top of Module 1 needed no knowledge of any of them.
 */
class CredentialApiController extends Controller
{
    public function __construct(private CredentialAuthority $authority)
    {
    }

    public function verify(Request $request): JsonResponse
    {
        $validator = validator($request->all(), Ifa::baseRules() + [
            'credentialId' => ['required', 'string', 'regex:/^LS-\d{4}-[0-9A-Z]{8}$/'],
            'detailFlag' => ['required', 'integer', 'in:1,2,3'],
        ]);

        if ($validator->fails()) {
            return Ifa::fail($request, [
                'credentialStatus' => 'NOT_FOUND',
                'errors' => $validator->errors()->all(),
            ]);
        }

        try {
            $result = $this->authority->verify($request->input('credentialId'));
            $certificate = $result['certificate'];
            $flag = (int) $request->input('detailFlag');

            $data = ['credentialStatus' => strtoupper($result['status'])];

            /*
             * detailFlag decides how much is disclosed. Level 1 answers only
             * "is this genuine", which is all an automated checker needs, and
             * discloses nothing about the holder.
             */
            if ($certificate !== null && $flag >= 2) {
                $data['holderName'] = $certificate->student->name;
                $data['courseTitle'] = $certificate->course?->title
                    ?? $certificate->learningPath?->title;
                $data['finalScore'] = round((float) $certificate->final_score, 2);
                $data['issuedDate'] = $certificate->issued_at->format('Y-m-d');
            }

            if ($certificate !== null && $flag === 3) {
                $data['credentialDetails'] = [
                    'issuer' => config('app.name'),
                    'expiresAt' => $certificate->expires_at?->format('Y-m-d'),
                    'revocationReason' => $certificate->revocation_reason,
                ];
            }

            return Ifa::success($request, $data);
        } catch (Throwable $e) {
            Log::error('getCredentialStatus failed', ['error' => $e->getMessage()]);

            return Ifa::error($request);
        }
    }
}
