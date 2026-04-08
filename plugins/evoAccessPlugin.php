<?php

use Illuminate\Support\Facades\Event;

/*
|--------------------------------------------------------------------------
| evoAccess — EVO manager menu integration
|--------------------------------------------------------------------------
|
| This file is loaded by EvoAccessServiceProvider::boot() when present.
| It hooks into EVO's OnManagerMenuPrerender event to inject an
| "Access" entry into the top-level manager menu under the configured
| category (default: 'tools').
|
| Disable by setting `evoAccess.manager_menu.enabled` to false in the
| published config.
|
*/

Event::listen('evolution.OnManagerMenuPrerender', function ($params) {
    if (!config('evoAccess.manager_menu.enabled', true)) {
        return;
    }

    $menu = $params['menu'] ?? [];

    $menu['evo_access'] = [
        'evo_access',
        config('evoAccess.manager_menu.category', 'tools'),
        '<i class="fa fa-shield-alt"></i> Access',
        url('access/matrix'),
        'EvoAccess — Roles & Permissions',
        '',
        '',
        '',
        0,
        500,
        '',
    ];

    $params['menu'] = $menu;

    return serialize($params['menu']);
});
