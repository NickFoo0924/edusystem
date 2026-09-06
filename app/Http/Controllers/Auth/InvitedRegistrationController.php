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
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

/**
 * The only route by which a user account can be created (EduSystem.md 1A).
 *
 * The recipient chooses their own name and password, but never their email or
 * their role -- both are fixed by the invitation an administrator issued, so a
 * student cannot arrive and register themselves as an instructor.
 */
class InvitedRegistrationController extends Controller
{
    /**
     * Show the registration form behind a valid token.
     */
    public function create(string $token): View
    {
        $invitation = $this->validInvitation($token);

        return view('auth.register-invited', compact('invitation'));
    }

    /**
     * Create the account the invitation authorises.
     */
    public function store(Request $request, string $token): RedirectResponse
    {
        $invitation = $this->validInvitation($token);

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        // One transaction: an account must never exist without its invitation
        // being marked accepted, or the same token could be redeemed twice.
        $user = DB::transaction(function () use ($request, $invitation) {
            $user = User::create([
                'name' => $request->string('name'),
                'email' => $invitation->email,
                'password' => $request->string('password'),
                'role' => $invitation->role,
                'is_active' => true,
            ]);

            $invitation->update(['accepted_at' => now()]);

            // The new account is its own actor: it is the subject of the event.
            ActivityLog::record('user.registered', $user, $user);

            return $user;
        });

        event(new Registered($user));

        Auth::login($user);

        return redirect()->route('dashboard');
    }

    /**
     * Resolve a token, or refuse the request.
     *
     * A token is only good if it exists, has not already been redeemed, has not
     * expired, and no account has since been created for its address.
     */
    private function validInvitation(string $token): Invitation
    {
        $invitation = Invitation::where('token', $token)->first();

        abort_if($invitation === null, 404, 'This invitation link is not valid.');
        abort_if($invitation->accepted_at !== null, 410, 'This invitation has already been used.');
        abort_if($invitation->expires_at->isPast(), 410, 'This invitation has expired. Ask an administrator for a new one.');
        abort_if(
            User::withTrashed()->where('email', $invitation->email)->exists(),
            409,
            'An account already exists for this email address.'
        );

        return $invitation;
    }
}
