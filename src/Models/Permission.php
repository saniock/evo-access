<?php

namespace Saniock\EvoAccess\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int    $id
 * @property string $name          e.g. 'orders.orders'
 * @property string $label
 * @property string $module
 * @property array  $actions       JSON list of allowed actions
 * @property bool   $is_orphaned
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

    protected $attributes = [
        'is_orphaned' => false,
    ];

    protected $casts = [
        'actions'     => 'array',
        'is_orphaned' => 'bool',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_orphaned', false);
    }

    public function scopeForModule($query, string $module)
    {
        return $query->where('module', $module);
    }
}
