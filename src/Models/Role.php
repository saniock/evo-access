<?php

namespace Saniock\EvoAccess\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Access-control role: a named bundle of permission grants.
 *
 * @property int    $id
 * @property string $name
 * @property string $label
 * @property string|null $description
 * @property bool   $is_system
 * @property int|null $created_by
 */
class Role extends Model
{
    protected $table = 'ea_roles';

    protected $fillable = [
        'name',
        'label',
        'description',
        'is_system',
        'created_by',
    ];

    protected $attributes = [
        'is_system' => false,
    ];

    protected $casts = [
        'is_system' => 'bool',
    ];

    public function grants(): HasMany
    {
        return $this->hasMany(RolePermissionAction::class, 'role_id');
    }

    public function userAssignments(): HasMany
    {
        return $this->hasMany(UserRole::class, 'role_id');
    }

    public function scopeSystem($query)
    {
        return $query->where('is_system', true);
    }
}
