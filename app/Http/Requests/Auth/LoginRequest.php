<?php

/**
 * LearnSync -- Form request validation
 *
 * Module 1: Identity, Access & Digital Credentialing
 *
 * @author Serena Lim Sze Kee
 */

namespace App\Http\Requests\Auth;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Consecutive failures that lock an account (EduSystem.md 1A).
     */
    public const MAX_FAILED_ATTEMPTS = 5;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();
        $this->ensureAccountIsNotLocked();

        if (! Auth::attempt($this->only('email', 'password'), $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey());

            // The Failed event listener in AppServiceProvider has already
            // incremented the counter; this turns the fifth strike into a lock.
            $this->lockAccountAfterRepeatedFailures();

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * Refuse a locked account before the password is even checked
     * (EduSystem.md 1A).
     *
     * Laravel's own rate limiter throttles by IP and forgets after a minute.
     * This is the persistent, per-account lock the specification asks for:
     * only an administrator can clear it.
     *
     * @throws ValidationException
     */
    protected function ensureAccountIsNotLocked(): void
    {
        $user = User::where('email', $this->string('email'))->first();

        if ($user === null || $user->locked_until === null || $user->locked_until->isPast()) {
            return;
        }

        throw ValidationException::withMessages([
            'email' => 'This account is locked after repeated failed sign-ins. Ask an administrator to unlock it.',
        ]);
    }

    /**
     * Lock the account once the failure count reaches the threshold.
     *
     * locked_until is set far in the future rather than to a timeout, because
     * 1A is explicit that only an administrator releases the lock. The date
     * merely gives the "is it locked" check something concrete to compare.
     */
    protected function lockAccountAfterRepeatedFailures(): void
    {
        $user = User::where('email', $this->string('email'))->first();

        if ($user === null || $user->locked_until !== null) {
            return;
        }

        if ($user->failed_login_attempts < self::MAX_FAILED_ATTEMPTS) {
            return;
        }

        $user->update(['locked_until' => now()->addYears(10)]);

        ActivityLog::record('user.locked_out', null, $user);
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }
}
