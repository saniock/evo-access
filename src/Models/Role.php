<?php

namespace Saniock\EvoAccess\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Access-control role: a named bundle of permission grants.
 *
 * Table: ea_roles
 *
 * @property int    $id
 * @property string $name        slug, unique (e.g. 'manager', 'warehouse_chief')
 * @property string $label       human title (e.g. 'Менеджер')
 * @property string|null $description
 * @property bool   $is_system   superadmin role flag — not user-editable
 */
class Role extends Model
{
    protected $table = 'ea_roles';

    protected $fillable = [
        'name',
        'label',
        'description',
        'is_system',
    ];

    protected $casts = [
        'is_system' => 'bool',
    ];
}
