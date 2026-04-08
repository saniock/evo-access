<?php

use Illuminate\Support\Facades\Event;

/*
|--------------------------------------------------------------------------
| evoAccess — EVO manager menu integration
|--------------------------------------------------------------------------
|
| This file is loaded by EvoAccessServiceProvider::boot() when present.
| It hooks into EVO's OnManagerMenuPrerender event to inject an
| "Access" entry into the top-level manager menu.
|
| Controlled by config('evoAccess.manager_menu.enabled'). Host projects
| can disable by publishing the config and flipping the flag.
|
| Intentionally a stub for now — the actual menu item is added during
| the implementation phase once the admin route is wired.
|
*/

Event::listen('evolution.OnManagerMenuPrerender', function ($params) {
    if (!config('evoAccess.manager_menu.enabled', true)) {
        return;
    }

    // TODO: inject a menu entry into $params['menu'] pointing at the
    //       evoAccess.matrix route. Requires the SVG icon from
    //       dirname(__DIR__) . '/images/access.svg' and a label from
    //       the evoAccess::global translation file.
});
