<?php

/**
 * LearnSync -- Authentication controller
 *
 * Module 1: Identity, Access & Digital Credentialing
 *
 * @author Serena Lim Sze Kee
 */

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\PasswordHistory;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class PasswordController extends Controller
{
    /**
     * Update the user's password.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validateWithBag('updatePassword', [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);

        $user = $request->user();

        // EduSystem.md 1A (stretch) -- a new password may not match the last
        // three. Checked before the change so the old hashes are still current.
        foreach (PasswordHistory::recentFor($user) as $previous) {
            if (Hash::check($validated['password'], $previous->password_hash)) {
                throw ValidationException::withMessages([
                    'password' => 'You have used this password recently. Choose one you have not used in your last '
                        .PasswordHistory::REMEMBERED.' passwords.',
                ])->errorBag('updatePassword');
            }
        }

        // Keep the outgoing password before replacing it.
        PasswordHistory::remember($user);

        $user->update([
            'password' => Hash::make($validated['password']),
            // Clears any administrator-forced reset (EnsurePasswordIsChanged).
            'must_change_password' => false,
        ]);

        ActivityLog::record('user.password_changed', null, $user);

        return back()->with('status', 'password-updated');
    }
}
