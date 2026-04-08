<?php

namespace Saniock\EvoAccess\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Immutable audit entry for access-control mutations.
 *
 * Written by AuditLogger::log() on every grant/revoke/role change.
 * Never updated, only inserted.
 *
 * Table: ea_audit_log
 *
 * @property int         $id
 * @property int         $actor_user_id
 * @property string      $action          'grant' | 'revoke' | 'create' | 'delete' | 'update' | 'rename'
 * @property int|null    $target_role_id
 * @property int|null    $target_user_id
 * @property int|null    $permission_id
 * @property string|null $old_value
 * @property string|null $new_value
 * @property array|null  $details         free-form structured payload
 * @property \DateTime   $created_at
 */
class AuditLog extends Model
{
    protected $table = 'ea_audit_log';

    public const UPDATED_AT = null;

    protected $fillable = [
        'actor_user_id',
        'action',
        'target_role_id',
        'target_user_id',
        'permission_id',
        'old_value',
        'new_value',
        'details',
    ];

    protected $casts = [
        'details'    => 'array',
        'created_at' => 'datetime',
    ];
}
