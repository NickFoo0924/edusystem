<?php

/**
 * LearnSync -- Web service consumer client
 *
 * Shared: project-wide infrastructure
 *
 * @author Serena Lim Sze Kee, Foo Chong Xian, Ong Shun Yan, Wong Siew Lam, Ong Kwong Wei
 */

namespace App\Support\Api;

use App\Support\Ifa;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The half of the integration every consuming module shares.
 *
 * Each module consumes a different member's service, but the mechanics are
 * identical every time: attach the API key, add the mandatory requestID and
 * timeStamp, send the call, check the IFA status before trusting anything,
 * and survive the provider being unavailable.
 *
 * Written once here so that five clients cannot drift into five different
 * ideas of what a failed call looks like.
 *
 * FAIL SOFT IS THE RULE. Every method returns null when the call does not
 * succeed, and never throws. A module must keep working when another member's
 * service is down: a lecturer should still be able to open a course page if
 * the analytics service is restarting, and a student must still receive the
 * certificate they earned if the course service cannot be reached.
 */
abstract class ServiceClient
{
    /**
     * The prefix stamped on this client's request IDs, so a call can be traced
     * back to the module that made it.
     */
    abstract protected function requestPrefix(): string;

    /**
     * Send a GET request to another module's service.
     *
     * @param  array<string, mixed>  $parameters
     * @return array<string, mixed>|null  the `data` block, or null on failure
     */
    protected function get(string $path, array $parameters): ?array
    {
        return $this->send('get', $path, $parameters);
    }

    /**
     * Send a POST request to another module's service.
     *
     * @param  array<string, mixed>  $parameters
     * @return array<string, mixed>|null
     */
    protected function post(string $path, array $parameters): ?array
    {
        return $this->send('post', $path, $parameters);
    }

    /**
     * @param  array<string, mixed>  $parameters
     * @return array<string, mixed>|null
     */
    private function send(string $method, string $path, array $parameters): ?array
    {
        $url = rtrim((string) config('services.internal_api.base_url'), '/').'/'.ltrim($path, '/');

        // The two fields the IFA makes mandatory on every request.
        $payload = $parameters + Ifa::requestEnvelope($this->requestPrefix());

        try {
            $response = $this->request()->{$method}($url, $payload);
        } catch (Throwable $e) {
            // A connection refused or a timeout lands here.
            Log::warning('Web service call failed', [
                'url' => $url,
                'requestID' => $payload['requestID'],
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        $body = $response->json();

        /*
         * The IFA status is the contract, not the HTTP code. A provider can
         * return 200 with a status of F, meaning it understood the question
         * perfectly well and could not answer it, so the status is what
         * decides whether the payload is trustworthy.
         */
        if (! Ifa::succeeded($body)) {
            Log::info('Web service returned a non-success status', [
                'url' => $url,
                'requestID' => $payload['requestID'],
                'status' => $body['status'] ?? 'none',
            ]);

            return null;
        }

        return $body['data'] ?? null;
    }

    /**
     * A configured HTTP client carrying the shared key.
     */
    private function request(): PendingRequest
    {
        return Http::acceptJson()
            ->timeout((int) config('services.internal_api.timeout', 10))
            ->withHeaders([
                'X-API-Key' => (string) config('services.internal_api.key'),
            ]);
    }
}
