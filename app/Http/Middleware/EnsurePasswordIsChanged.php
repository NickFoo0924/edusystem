<?php

/**
 * LearnSync -- HTTP middleware
 *
 * Module 1: Identity, Access & Digital Credentialing
 *
 * @author Serena Lim Sze Kee
 */

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Holds a user on the profile page until they replace a password that was set
 * for them (EduSystem.md 1A, "forced password reset on first login").
 *
 * An administrator can tick must_change_password on an account; from then on
 * every page bounces to the profile until a new password is saved.
 */
class EnsurePasswordIsChanged
{
    /**
     * Routes that must stay reachable, or the user would be trapped with no way
     * to comply and no way to leave.
     */
    private const ALLOWED = [
        'profile.edit',
        'profile.update',
        'password.update',
        'logout',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->must_change_password) {
            return $next($request);
        }

        if (in_array($request->route()?->getName(), self::ALLOWED, true)) {
            return $next($request);
        }

        return redirect()->route('profile.edit')
            ->with('error', 'Choose a new password before continuing.');
    }
}
