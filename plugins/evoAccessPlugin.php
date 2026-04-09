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
| Two layers of gating:
|   1. config('evoAccess.manager_menu.enabled')   — global on/off
|   2. config('evoAccess.web_user_whitelist')     — soft-rollout list
|      of EVO manager user IDs that should see the entry. Empty list
|      means "everyone sees it" (general-rollout mode).
|
| The gates live in this plugin file (and not in the SP boot) on
| purpose: by the time OnManagerMenuPrerender fires we are guaranteed
| to be inside an EVO manager request, so the manager session is
| already bootstrapped and `evo()->getLoginUserID('mgr')` returns a
| real user ID. Putting the gate in the SP boot would block routes on
| direct /access/* URL hits, where the manager session has not yet
| been initialised.
|
*/

Event::listen('evolution.OnManagerMenuPrerender', function ($params) {
    if (!config('evoAccess.manager_menu.enabled', true)) {
        return;
    }

    if (!evoAccessUserIsWhitelisted()) {
        return;
    }

    $menu = $params['menu'] ?? [];

    // NB: relative path used intentionally — Laravel's url() helper
    // depends on Illuminate\Contracts\Routing\UrlGenerator which is
    // not bound in EVO's manager context. The browser resolves
    // '/access/matrix' against the current host, which is correct
    // since the routes live at the document root.
    $menu['evo_access'] = [
        'evo_access',
        config('evoAccess.manager_menu.category', 'tools'),
        '<i class="fa fa-shield-alt"></i> Access',
        '/access/matrix',
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

if (!function_exists('evoAccessUserIsWhitelisted')) {
    /**
     * Soft-rollout gate: empty whitelist = "everyone gets the menu
     * item", non-empty whitelist = only listed EVO manager user IDs
     * see it. Lives at file scope so it is only defined once even if
     * the plugin file is loaded multiple times in the same request.
     */
    function evoAccessUserIsWhitelisted(): bool
    {
        $whitelist = (array)config('evoAccess.web_user_whitelist', []);

        if (empty($whitelist)) {
            return true;
        }

        if (!function_exists('evo')) {
            return false;
        }

        $userId = (int)evo()->getLoginUserID('mgr');

        return $userId > 0 && in_array($userId, $whitelist, true);
    }
}
