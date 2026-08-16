<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single grant: this permission is held by this role.
 *
 * Modelled explicitly rather than as an anonymous pivot because the other side
 * is a role enum, not a roles table, so belongsToMany has nothing to point at.
 * Giving the pivot a model keeps the admin permission grid pure Eloquent.
 */
class PermissionRole extends Model
{
    use HasFactory;

    protected $table = 'permission_role';

    protected $fillable = [
        'permission_id',
        'role',
    ];

    public function permission(): BelongsTo
    {
        return $this->belongsTo(Permission::class);
    }
}
