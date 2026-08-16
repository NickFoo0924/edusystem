<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * MODULE 1 (1A) -- account lifecycle.
 *
 * Activate, deactivate, unlock, change role and soft-delete. These are the
 * actions EduSystem.md 1A names as auditable, so every one writes an
 * ActivityLog row.
 *
 * Every action is deliberately hard to trigger by accident:
 *   1. the list only ever links to a confirmation page, never to the action
 *   2. that page names the account and spells out the consequence
 *   3. the administrator must re-enter their own password to proceed
 * A stray click can therefore never change an account. The password is checked
 * with Laravel's `current_password` rule, so it is the administrator's own --
 * no shared secret is stored anywhere.
 */
class UserController extends Controller
{
    /**
     * action key => [verb shown, consequence explained, is it destructive]
     */
    public const ACTIONS = [
        'deactivate' => ['Deactivate account', 'They will be signed out immediately and blocked from every page until reactivated.', true],
        'activate' => ['Reactivate account', 'They will be able to sign in and use the system again.', false],
        'unlock' => ['Unlock account', 'The failed sign-in counter is cleared and they can attempt to log in again.', false],
        'role' => ['Change role', 'Their permissions change at once, which may grant or remove administrative access.', true],
        'delete' => ['Delete account', 'The account is soft-deleted. It disappears from the system but can be restored, and the audit trail keeps their name.', true],
        'restore' => ['Restore account', 'The account becomes usable again.', false],
    ];

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('user.activate'), 403);

        return view('users.index', [
            'users' => User::withTrashed()->orderBy('role')->orderBy('name')->get(),
            'actions' => self::ACTIONS,
        ]);
    }

    /**
     * The interstitial. Nothing has happened yet when this page is shown.
     */
    public function confirm(Request $request, string $action, int $userId): View
    {
        abort_unless(array_key_exists($action, self::ACTIONS), 404);
        abort_unless($request->user()->can($this->permissionFor($action)), 403);

        $user = User::withTrashed()->findOrFail($userId);
        $this->refuseSelf($request, $user);

        $newRole = $request->query('role');

        if ($action === 'role') {
            abort_unless(in_array($newRole, ['admin', 'instructor', 'student'], true), 404);
        }

        return view('users.confirm', [
            'user' => $user,
            'action' => $action,
            'newRole' => $newRole,
            'label' => self::ACTIONS[$action][0],
            'consequence' => self::ACTIONS[$action][1],
            'destructive' => self::ACTIONS[$action][2],
        ]);
    }

    /**
     * Carry out a confirmed action.
     *
     * One entry point for all of them, so the password check can never be
     * forgotten on a new action -- it happens before the switch.
     */
    public function perform(Request $request, string $action, int $userId): RedirectResponse
    {
        abort_unless(array_key_exists($action, self::ACTIONS), 404);
        abort_unless($request->user()->can($this->permissionFor($action)), 403);

        $user = User::withTrashed()->findOrFail($userId);
        $this->refuseSelf($request, $user);

        // The double confirmation. `current_password` compares against the
        // signed-in administrator's own hash.
        $request->validate([
            'confirm_password' => ['required', 'current_password'],
        ], [
            'confirm_password.required' => 'Enter your password to confirm.',
            'confirm_password.current_password' => 'That is not your password. Nothing was changed.',
        ]);

        $message = match ($action) {
            'deactivate' => $this->setActive($user, false),
            'activate' => $this->setActive($user, true),
            'unlock' => $this->unlock($user),
            'role' => $this->changeRole($request, $user),
            'delete' => $this->softDelete($user),
            'restore' => $this->restore($user),
        };

        return redirect()->route('users.index')->with('success', $message);
    }

    private function setActive(User $user, bool $active): string
    {
        $user->update(['is_active' => $active]);

        ActivityLog::record($active ? 'user.activated' : 'user.deactivated', $user);

        return "{$user->name} is now ".($active ? 'active' : 'deactivated').'.';
    }

    /**
     * Section 1A: five failed attempts lock an account and only an
     * administrator can release it.
     */
    private function unlock(User $user): string
    {
        $user->update(['locked_until' => null, 'failed_login_attempts' => 0]);

        ActivityLog::record('user.unlocked', $user);

        return "{$user->name} unlocked.";
    }

    private function changeRole(Request $request, User $user): string
    {
        $data = $request->validate([
            'role' => ['required', 'in:admin,instructor,student'],
        ]);

        $previous = $user->role;

        if ($previous === $data['role']) {
            return "{$user->name} was already a {$previous}. Nothing changed.";
        }

        $user->update($data);

        ActivityLog::record('user.role_changed', $user);

        return "{$user->name} changed from {$previous} to {$data['role']}.";
    }

    private function softDelete(User $user): string
    {
        $user->delete();

        ActivityLog::record('user.deleted', $user);

        return "{$user->name} deleted. The account can still be restored.";
    }

    private function restore(User $user): string
    {
        $user->restore();

        ActivityLog::record('user.restored', $user);

        return "{$user->name} restored.";
    }

    /**
     * Which permission key each action needs.
     */
    private function permissionFor(string $action): string
    {
        return match ($action) {
            'delete', 'restore' => 'user.delete',
            'unlock' => 'user.unlock',
            default => 'user.activate',
        };
    }

    /**
     * An administrator locking themselves out or demoting themselves would need
     * database access to undo, so it is refused outright.
     */
    private function refuseSelf(Request $request, User $user): void
    {
        abort_if($user->id === $request->user()->id, 403, 'You cannot change your own account from this screen.');
    }
}
