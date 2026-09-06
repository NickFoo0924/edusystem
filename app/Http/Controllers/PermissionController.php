<?php

/**
 * LearnSync -- HTTP controller
 *
 * Module 1: Identity, Access & Digital Credentialing
 *
 * @author Serena Lim Sze Kee
 */

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Permission;
use App\Models\PermissionRole;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * BUILD PRIORITY ITEM 3 -- the database-driven permission matrix.
 *
 * This screen is what turns EduSystem.md Section 7 from a table in a document
 * into working configuration. Every grant is a permission_role row, the Gate in
 * AppServiceProvider resolves $user->can(...) against those rows, and this
 * controller is the only place they are edited.
 *
 * Guarded by can:permission.manage route middleware.
 */
class PermissionController extends Controller
{
    /**
     * The three roles the matrix has columns for.
     */
    public const ROLES = ['admin', 'instructor', 'student'];

    /**
     * The one grant that may never be removed.
     *
     * Without it an administrator could un-tick their own right to edit the
     * matrix and permanently lock every account out of this screen, with no way
     * back except editing the database by hand.
     */
    private const PROTECTED_GRANT = ['permission.manage', 'admin'];

    /**
     * Render the checkbox grid, grouped exactly as the permissions are grouped.
     */
    public function index(): View
    {
        $permissions = Permission::with('permissionRoles')
            ->orderBy('group')
            ->orderBy('key')
            ->get()
            ->groupBy('group');

        return view('permissions.index', [
            'groupedPermissions' => $permissions,
            'roles' => self::ROLES,
            'protectedKey' => self::PROTECTED_GRANT[0],
            'protectedRole' => self::PROTECTED_GRANT[1],
        ]);
    }

    /**
     * Save the whole grid in one submit.
     *
     * The form posts grants[permission_id][] = role. A permission with no boxes
     * ticked is absent from the payload entirely, which is why the loop walks
     * every permission rather than only the submitted ones -- otherwise
     * clearing a row would silently do nothing.
     */
    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'grants' => ['nullable', 'array'],
            'grants.*' => ['array'],
            'grants.*.*' => ['in:'.implode(',', self::ROLES)],
        ]);

        $submitted = $request->input('grants', []);
        $changes = 0;

        foreach (Permission::all() as $permission) {
            $roles = array_values(array_unique($submitted[$permission->id] ?? []));

            if ($permission->key === self::PROTECTED_GRANT[0] && ! in_array(self::PROTECTED_GRANT[1], $roles, true)) {
                $roles[] = self::PROTECTED_GRANT[1];
            }

            // Revoke anything no longer ticked. An empty $roles removes them all.
            $changes += PermissionRole::where('permission_id', $permission->id)
                ->whereNotIn('role', $roles)
                ->delete();

            // Grant anything newly ticked. firstOrCreate leans on the composite
            // unique key so a double submit cannot duplicate a grant.
            foreach ($roles as $role) {
                $grant = PermissionRole::firstOrCreate([
                    'permission_id' => $permission->id,
                    'role' => $role,
                ]);

                if ($grant->wasRecentlyCreated) {
                    $changes++;
                }
            }
        }

        if ($changes > 0) {
            ActivityLog::record('permission.matrix_updated');
        }

        return redirect()->route('permissions.index')
            ->with('success', $changes === 0
                ? 'No changes to save.'
                : "Permission matrix updated ({$changes} change".($changes === 1 ? '' : 's').').');
    }
}
