<?php

/**
 * LearnSync -- HTTP middleware
 *
 * Module 1: Identity, Access & Digital Credentialing
 *
 * @author Serena Lim Sze Kee
 */

namespace App\Http\Middleware;

use App\Models\ActivityLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Turns a deactivated or locked account away from the whole application
 * (EduSystem.md 1A).
 *
 * The permission Gate already returns false for every ability when is_active is
 * false, but that alone only empties the pages -- the user could still browse
 * the shell of the app. This ends their session instead, which is what
 * "deactivated" is supposed to mean.
 */
class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        $isLocked = $user->locked_until !== null && $user->locked_until->isFuture();

        /*
         * Only an explicit false blocks. A null means the attribute was never
         * loaded onto this instance -- the column is NOT NULL with a default of
         * true, so "unknown" must not be read as "deactivated". Reading it that
         * way locked out any user built in memory rather than fetched.
         */
        $isDeactivated = $user->is_active !== null && ! $user->is_active;

        if (! $isDeactivated && ! $isLocked) {
            return $next($request);
        }

        ActivityLog::record($isLocked ? 'auth.blocked_locked' : 'auth.blocked_inactive', null, $user);


        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->withErrors([
            'email' => $isLocked
                ? 'This account is locked. Ask an administrator to unlock it.'
                : 'This account has been deactivated. Contact an administrator.',
        ]);
    }
}
