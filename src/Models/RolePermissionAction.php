<?php

namespace Saniock\EvoAccess\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Normalized grant: one (role × permission × action) tuple.
 *
 * Presence of a row means "role X has action Y on permission Z".
 * Absence means "not granted". Per-user revokes live in UserOverride.
 *
 * Table: ea_role_permission_actions
 * Composite PK: (role_id, permission_id, action)
 *
 * @property int         $role_id
 * @property int         $permission_id
 * @property string      $action
 * @property int|null    $granted_by
 * @property \DateTime   $granted_at
 */
class RolePermissionAction extends Model
{
    protected $table = 'ea_role_permission_actions';

    public $incrementing = false;

    public $timestamps = false;

    protected $primaryKey = null;

    protected $fillable = [
        'role_id',
        'permission_id',
        'action',
        'granted_by',
        'granted_at',
    ];

    protected $casts = [
        'granted_at' => 'datetime',
    ];
}
