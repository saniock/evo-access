<?php

namespace Saniock\EvoAccess\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * User → role assignment (flat model: one user has exactly one role).
 *
 * Table: ea_user_roles
 * PK: user_id (1 row per user)
 *
 * @property int       $user_id      EVO user.id
 * @property int       $role_id      FK → ea_roles.id
 * @property int|null  $assigned_by
 * @property \DateTime $assigned_at
 */
class UserRole extends Model
{
    protected $table = 'ea_user_roles';

    protected $primaryKey = 'user_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'role_id',
        'assigned_by',
        'assigned_at',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
    ];

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }
}
