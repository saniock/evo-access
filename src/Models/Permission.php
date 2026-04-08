<?php

namespace Saniock\EvoAccess\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Known permission in the system (synced from the in-memory catalog
 * by SyncPermissionsCommand). Existence here is a precondition for
 * granting it to a role.
 *
 * Table: ea_permissions
 *
 * @property int    $id
 * @property string $name          e.g. 'orders.orders'
 * @property string $label         human description
 * @property string $module        grouping key for UI accordion
 * @property array  $actions       json list of allowed actions
 * @property bool   $is_orphaned   1 when it disappeared from the in-memory catalog
 */
class Permission extends Model
{
    protected $table = 'ea_permissions';

    protected $fillable = [
        'name',
        'label',
        'module',
        'actions',
        'is_orphaned',
    ];

    protected $casts = [
        'actions'     => 'array',
        'is_orphaned' => 'bool',
    ];
}
