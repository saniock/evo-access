<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Bootstrap superadmin user IDs
    |--------------------------------------------------------------------------
    |
    | EVO user IDs that are unconditionally mapped to the system
    | superadmin role on first migration, so the admin UI is never
    | accidentally locked out. These users keep their superadmin
    | status even if the database rows are wiped.
    |
    */

    'bootstrap_superadmin_user_ids' => [
        // 7,  // example: primary admin EVO user.id
    ],

    /*
    |--------------------------------------------------------------------------
    | Default action vocabulary
    |--------------------------------------------------------------------------
    |
    | Recommended standard action names. Modules are free to declare
    | additional custom actions per permission (e.g. 'refund', 'publish').
    |
    */

    /*
    | Recommended action vocabulary. Modules are free to declare any
    | lowercase snake_case slugs (matches /^[a-z][a-z0-9_]*$/), but
    | keeping to this list keeps the matrix UI consistent across modules.
    | Translation strings are pre-shipped for every entry in lang/{locale}/global.php
    | under the "action" key.
    */
    'standard_actions' => [
        'view',
        'create',
        'edit',
        'delete',
        'export',
        'import',
        'bulk',
    ],

    /*
    |--------------------------------------------------------------------------
    | System role slug
    |--------------------------------------------------------------------------
    |
    | The slug of the hardcoded superadmin role. Rows in ea_roles
    | matching this name are flagged is_system=1 and bypass the
    | permission matrix entirely.
    |
    */

    'superadmin_role_slug' => 'superadmin',

    /*
    |--------------------------------------------------------------------------
    | Manager menu integration
    |--------------------------------------------------------------------------
    |
    | Toggle whether the package injects an "Access" item into the EVO
    | manager top menu via plugins/evoAccessPlugin.php. Turn off to
    | self-host the UI elsewhere.
    |
    */

    'manager_menu' => [
        'enabled'  => true,
        'category' => 'tools',
        'label'    => 'Access',
        'route'    => 'evoAccess.matrix',
    ],

    /*
    |--------------------------------------------------------------------------
    | Web user whitelist (rollout gate)
    |--------------------------------------------------------------------------
    |
    | EVO manager user IDs that should see the package integration on
    | web requests. When non-empty, only these users get the menu entry,
    | routes, observers, and EVO menu plugin — for everyone else the
    | package is invisible. This lets you ship a half-finished package
    | alongside live managers without disturbing their workflow.
    |
    | Empty array (default) means "no gating, everyone gets it" — flip
    | the switch by overriding this in your consumer config once the
    | package is smoke-tested end-to-end and ready for general rollout.
    |
    | CLI is never gated — `php artisan migrate` and the evoaccess:*
    | commands always work regardless of this setting.
    |
    */

    'web_user_whitelist' => [],

    /*
    |--------------------------------------------------------------------------
    | Available UI locales
    |--------------------------------------------------------------------------
    |
    | Locales that can be selected in the admin UI language picker. Keys
    | are ISO codes (used for lang file lookup), values are display
    | labels shown in the picker.
    |
    | The 'ua' → 'uk' alias is handled automatically in BaseController,
    | so you don't need to list 'ua' here — list 'uk' instead even if
    | your EVO project still uses 'ua' as its system locale.
    |
    */

    'available_locales' => [
        'uk' => 'Українська',
        'en' => 'English',
        'ru' => 'Русский',
    ],

];
