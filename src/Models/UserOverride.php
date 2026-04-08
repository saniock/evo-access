<?php

namespace Saniock\EvoAccess\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Per-user exception to the role-assigned permission set.
 *
 * mode = 'grant'  → adds the action on top of whatever the role gives
 * mode = 'revoke' → removes the action even if the role grants it
 *
 * Resolver order: role grants first, then revokes, then grants,
 * so a revoke never masks a future role grant that's added after.
 *
 * Table: ea_user_overrides
 * Composite PK: (user_id, permission_id, action)
 *
 * @property int       $user_id
 * @property int       $permission_id
 * @property string    $action
 * @property string    $mode          'grant' | 'revoke'
 * @property string|null $reason
 * @property int|null  $created_by
 * @property \DateTime $created_at
 */
class UserOverride extends Model
{
    protected $table = 'ea_user_overrides';

    public $incrementing = false;

    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'permission_id',
        'action',
        'mode',
        'reason',
        'created_by',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];
}
