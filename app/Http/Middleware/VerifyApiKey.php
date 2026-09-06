<?php

/**
 * LearnSync -- HTTP middleware
 *
 * Shared: project-wide infrastructure
 *
 * @author Serena Lim Sze Kee, Foo Chong Xian, Ong Shun Yan, Wong Siew Lam, Ong Kwong Wei
 */

namespace App\Http\Middleware;

use App\Support\Ifa;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the module-to-module web services with a shared key.
 *
 * Four of the five services return data that is not public: a student's quiz
 * result, a class's analytics, a course roster count, and the ability to write
 * somebody's notification. Publishing those on an open URL would be a worse
 * hole than any this project closes elsewhere, so a caller must present the
 * key in an X-API-Key header.
 *
 * The credential verification service is deliberately NOT behind this
 * middleware. EduSystem.md Section 7 gives an unauthenticated guest exactly
 * one entitlement, which is checking whether a certificate is genuine, and a
 * verification service that required a key would defeat the entire point of
 * public verification.
 *
 * hash_equals() is used rather than === so the comparison takes the same time
 * whether the key is wrong at the first character or the last, closing the
 * timing side-channel an attacker could otherwise use to guess it.
 */
class VerifyApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('services.internal_api.key');
        $presented = (string) $request->header('X-API-Key', '');

        if ($expected === '' || ! hash_equals($expected, $presented)) {
            return response()->json([
                'status' => 'F',
                'timeStamp' => now()->utc()->format(Ifa::TIMESTAMP_FORMAT),
                'data' => [
                    'requestID' => (string) $request->input('requestID', ''),
                    'message' => 'A valid X-API-Key header is required for this service.',
                ],
            ], 401);
        }

        return $next($request);
    }
}
