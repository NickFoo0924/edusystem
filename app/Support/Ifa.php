<?php

/**
 * LearnSync -- Support helper
 *
 * Shared: project-wide infrastructure
 *
 * @author Serena Lim Sze Kee, Foo Chong Xian, Ong Shun Yan, Wong Siew Lam, Ong Kwong Wei
 */

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * The Interface Agreement (IFA) shared by every module's web service.
 *
 * The assignment fixes two rules that apply to all five services, and putting
 * them here is what stops five people implementing them five slightly
 * different ways:
 *
 *   Every REQUEST carries a requestID and a timeStamp, so a call can be
 *   traced from the caller's log to the provider's log.
 *
 *   Every RESPONSE carries a status of S, F or E, and a timeStamp saying when
 *   the answer was produced. The requestID is echoed back so the caller can
 *   match an answer to the question it asked.
 *
 * Status meanings, applied the same way by all five services:
 *   S  the request was understood and the answer is in `data`
 *   F  the request was understood but could not be satisfied, for example a
 *      credential ID that does not exist, or parameters that failed validation
 *   E  something went wrong inside the provider, and it is not the caller's fault
 */
class Ifa
{
    /**
     * The timestamp format both sides agree on (ISO-8601, UTC).
     */
    public const TIMESTAMP_FORMAT = 'Y-m-d\TH:i:s\Z';

    /**
     * Validation rules every request must satisfy, whichever service it is for.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function baseRules(): array
    {
        return [
            'requestID' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9\-_]+$/'],
            'timeStamp' => ['required', 'date'],
        ];
    }

    /**
     * A successful answer.
     *
     * @param  array<string, mixed>  $data
     */
    public static function success(Request $request, array $data): JsonResponse
    {
        return self::respond('S', $request, $data, 200);
    }

    /**
     * The request was understood but cannot be satisfied.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fail(Request $request, array $data, int $httpCode = 400): JsonResponse
    {
        return self::respond('F', $request, $data, $httpCode);
    }

    /**
     * The provider broke. Deliberately does not leak the exception message to
     * the caller, because a stack trace or an SQL fragment in an API response
     * is an information disclosure vulnerability. The detail goes to the log.
     *
     * @param  array<string, mixed>  $data
     */
    public static function error(Request $request, array $data = []): JsonResponse
    {
        return self::respond('E', $request, $data + [
            'message' => 'The service could not complete the request.',
        ], 500);
    }

    /**
     * Build the envelope every response shares.
     *
     * @param  array<string, mixed>  $data
     */
    private static function respond(string $status, Request $request, array $data, int $httpCode): JsonResponse
    {
        return response()->json([
            'status' => $status,
            'timeStamp' => now()->utc()->format(self::TIMESTAMP_FORMAT),
            'data' => array_merge(
                ['requestID' => (string) $request->input('requestID', '')],
                $data
            ),
        ], $httpCode);
    }

    /**
     * The two mandatory fields a caller must send, ready to merge into the
     * query string of an outgoing request.
     *
     * @return array{requestID: string, timeStamp: string}
     */
    public static function requestEnvelope(string $prefix): array
    {
        return [
            'requestID' => $prefix.'-'.Str::upper(Str::random(10)),
            'timeStamp' => now()->utc()->format(self::TIMESTAMP_FORMAT),
        ];
    }

    /**
     * Did a provider answer successfully?
     *
     * Callers must check this rather than relying on the HTTP code alone: the
     * IFA status is the contract, and a provider that answers "F" has given a
     * perfectly valid HTTP response to a question it could not satisfy.
     *
     * @param  array<string, mixed>|null  $payload
     */
    public static function succeeded(?array $payload): bool
    {
        return is_array($payload) && ($payload['status'] ?? null) === 'S';
    }
}
