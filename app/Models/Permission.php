<?php

/**
 * LearnSync -- Eloquent model
 *
 * Module 1: Identity, Access & Digital Credentialing
 *
 * @author Serena Lim Sze Kee
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One row of the RBAC matrix in EduSystem.md Section 7, e.g. key
 * "certificate.revoke" in the group "Credentialing".
 */
class Permission extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'label',
        'group',
    ];

    /**
     * The role grants attached to this permission.
     */
    public function permissionRoles(): HasMany
    {
        return $this->hasMany(PermissionRole::class);
    }
}
