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

    'standard_actions' => [
        'view',
        'create',
        'update',
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

];
