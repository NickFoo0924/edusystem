<?php

namespace App\Http\Controllers;

use App\Mail\InvitationMail;
use App\Models\ActivityLog;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

/**
 * BUILD PRIORITY ITEM 4 -- invitation-based registration (EduSystem.md 1A).
 *
 * Public self-signup is disabled: Breeze's open GET/POST /register pair has been
 * deleted. The only way an account comes into existence is that an
 * administrator issues an invitation here, and the recipient follows the
 * tokenised link before it expires.
 *
 * Guarded by can:invitation.issue route middleware, so the right is a database
 * row in the permission matrix like every other.
 */
class InvitationController extends Controller
{
    /**
     * Roles an administrator may invite someone as.
     */
    public const ROLES = ['admin', 'instructor', 'student'];

    /**
     * How long an unaccepted invitation stays usable, unless told otherwise.
     */
    private const DEFAULT_EXPIRY_DAYS = 7;

    public function index(): View
    {
        $invitations = Invitation::with('inviter')
            ->orderByDesc('created_at')
            ->get();

        return view('invitations.index', compact('invitations'));
    }

    public function create(): View
    {
        return view('invitations.create', [
            'roles' => self::ROLES,
            'defaultDays' => self::DEFAULT_EXPIRY_DAYS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            // Not unique on invitations: an expired invite may legitimately be
            // reissued. It is unique against users, because an existing account
            // does not need inviting.
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', 'in:'.implode(',', self::ROLES)],
            'expires_in_days' => ['required', 'integer', 'min:1', 'max:90'],
        ]);

        // Any earlier pending invitation for this address is superseded, so a
        // recipient can never hold two live tokens at once.
        Invitation::where('email', $data['email'])
            ->whereNull('accepted_at')
            ->delete();

        $invitation = Invitation::create([
            'email' => $data['email'],
            'role' => $data['role'],
            'token' => Str::random(64),
            'invited_by' => $request->user()->id,
            'expires_at' => now()->addDays((int) $data['expires_in_days']),
        ]);

        // MAIL_MAILER=log in this environment, so the message is written to
        // storage/logs/laravel.log rather than actually sent anywhere.
        //
        // A mail failure must not lose the invitation: the token is already
        // saved and the administrator can copy the link straight off the next
        // screen, so a broken mail server degrades the flow instead of blocking it.
        ActivityLog::record('invitation.issued', $invitation);

        try {
            Mail::to($invitation->email)->send(new InvitationMail($invitation));
            $message = "Invitation sent to {$invitation->email}.";
        } catch (Throwable $e) {
            Log::error('Invitation email could not be sent.', [
                'invitation_id' => $invitation->id,
                'error' => $e->getMessage(),
            ]);
            $message = "Invitation created for {$invitation->email}, but the email could not be sent. Copy the link below and send it yourself.";
        }

        return redirect()->route('invitations.index')
            ->with('success', $message)
            ->with('highlight', $invitation->id);
    }

    /**
     * [STRETCH] Bulk import (EduSystem.md 1A).
     *
     * An administrator uploads a class list and the system issues an invitation
     * per row in one operation. Rows that cannot be used are reported back
     * rather than silently skipped, so a typo in a CSV never disappears.
     */
    public function bulkStore(Request $request): RedirectResponse
    {
        $request->validate([
            'csv' => ['required', 'file', 'mimes:csv,txt', 'max:1024'],
            'role' => ['required', 'in:'.implode(',', self::ROLES)],
        ]);

        $handle = fopen($request->file('csv')->getRealPath(), 'r');
        $issued = 0;
        $skipped = [];
        $line = 0;

        while (($row = fgetcsv($handle)) !== false) {
            $line++;
            $email = trim((string) ($row[0] ?? ''));

            // Tolerate a header row.
            if ($line === 1 && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $skipped[] = "line {$line}: \"{$email}\" is not an email address";

                continue;
            }

            if (User::where('email', $email)->exists()) {
                $skipped[] = "line {$line}: {$email} already has an account";

                continue;
            }

            Invitation::where('email', $email)->whereNull('accepted_at')->delete();

            $invitation = Invitation::create([
                'email' => $email,
                'role' => $request->string('role'),
                'token' => Str::random(64),
                'invited_by' => $request->user()->id,
                'expires_at' => now()->addDays(self::DEFAULT_EXPIRY_DAYS),
            ]);

            try {
                Mail::to($invitation->email)->send(new InvitationMail($invitation));
            } catch (Throwable $e) {
                Log::error('Bulk invitation email failed.', ['email' => $email, 'error' => $e->getMessage()]);
            }

            $issued++;
        }

        fclose($handle);

        ActivityLog::record('invitation.bulk_imported');

        $message = "{$issued} invitation".($issued === 1 ? '' : 's').' issued.';

        if ($skipped !== []) {
            $message .= ' Skipped '.count($skipped).': '.implode('; ', array_slice($skipped, 0, 5));
            if (count($skipped) > 5) {
                $message .= ' … and '.(count($skipped) - 5).' more';
            }
        }

        return redirect()->route('invitations.index')
            ->with($skipped === [] ? 'success' : 'error', $message);
    }

    /**
     * Withdraw an invitation. An already-accepted one is kept as a record of
     * how that account came to exist.
     */
    public function destroy(Invitation $invitation): RedirectResponse
    {
        if ($invitation->accepted_at !== null) {
            return redirect()->route('invitations.index')
                ->with('error', 'That invitation has already been accepted and cannot be withdrawn.');
        }

        $email = $invitation->email;
        ActivityLog::record('invitation.withdrawn', $invitation);
        $invitation->delete();

        return redirect()->route('invitations.index')
            ->with('success', "Invitation to {$email} withdrawn.");
    }
}
