<?php

return [
    'title' => 'Access',

    'nav' => [
        'users'  => 'Users',
        'roles'  => 'Roles',
        'matrix' => 'Matrix',
        'audit'  => 'Audit',
        'docs'   => 'Documentation',
    ],

    'common' => [
        'save'     => 'Save',
        'cancel'   => 'Cancel',
        'delete'   => 'Delete',
        'edit'     => 'Edit',
        'create'   => 'Create',
        'close'    => 'Close',
        'search'   => 'Search',
        'filter'   => 'Filter',
        'yes'      => 'Yes',
        'no'       => 'No',
        'loading'  => 'Loading…',
        'error'    => 'Error',
        'language' => 'Language',
    ],

    'users' => [
        'title'              => 'Users',
        'search_placeholder' => 'Search manager…',
        'filter_by_role'     => 'Filter by role',
        'all_roles'          => 'All roles',
        'column' => [
            'id'        => 'ID',
            'manager'   => 'Manager',
            'role'      => 'Role',
            'modules'   => 'Modules',
            'grants'    => 'Grants',
            'overrides' => 'Overrides',
        ],
        'popup' => [
            'role_label'     => 'Role:',
            'permission'     => 'Permission',
            'legend' => [
                'from_role'       => 'From role',
                'override_grant'  => 'Override +',
                'override_revoke' => 'Override −',
                'no_access'       => 'No access',
            ],
        ],
        'save_success'     => 'Permissions saved',
        'manager_no_name'  => 'Manager #:id',
    ],

    'roles' => [
        'title'         => 'Roles',
        'create_button' => 'Create Role',
        'column' => [
            'name'        => 'Name',
            'label'       => 'Label',
            'description' => 'Description',
            'users'       => 'Users',
            'system'      => 'System',
        ],
        'form' => [
            'create_title'        => 'Create Role',
            'edit_title'          => 'Edit Role: :name',
            'name_label'          => 'Name (slug)',
            'name_placeholder'    => 'e.g. warehouse_manager',
            'label_label'         => 'Label',
            'label_placeholder'   => 'Display name',
            'description_label'   => 'Description',
        ],
        'clone' => [
            'title'  => 'Clone Role: :name',
            'hint'   => 'Cloning from <b>:name</b>. All permission grants will be copied.',
            'button' => 'Clone',
        ],
        'delete' => [
            'confirm_title'     => 'Delete Role',
            'confirm_text'      => 'Delete role ":name"? This cannot be undone.',
            'reassign_title'    => 'Delete Role: :name',
            'reassign_text'     => 'Role <b>:name</b> has <b>:count</b> assigned user(s). Reassign them to another role before deleting:',
            'reassign_new_role' => 'New role',
            'reassign_button'   => 'Reassign & Delete',
            'select_role'       => 'Select a role',
        ],
        'msg' => [
            'created'    => 'Role created',
            'updated'    => 'Role updated',
            'cloned'     => 'Role cloned',
            'deleted'    => 'Role deleted',
            'reassigned' => ':count user(s) reassigned, role deleted',
        ],
    ],

    'matrix' => [
        'title'         => 'Permission Matrix',
        'select_role'   => 'Select a role',
        'column' => [
            'module'     => 'Module',
            'permission' => 'Permission',
        ],
        'system_notice' => 'system — bypasses matrix',
    ],

    'audit' => [
        'title' => 'Audit Log',
        'filter' => [
            'from'        => 'From',
            'to'          => 'To',
            'actor_id'    => 'Actor ID',
            'action_type' => 'Action type',
            'all_actions' => 'All actions',
            'button'      => 'Filter',
        ],
        'action' => [
            'grant'             => 'Grant',
            'revoke'            => 'Revoke',
            'user_assigned'     => 'User assigned',
            'user_role_changed' => 'Role changed',
            'role_created'      => 'Role created',
            'role_deleted'      => 'Role deleted',
            'role_cloned'       => 'Role cloned',
            'override_grant'    => 'Override grant',
            'override_revoke'   => 'Override revoke',
            'override_removed'  => 'Override removed',
        ],
        'column' => [
            'datetime'      => 'Date/Time',
            'actor'         => 'Actor',
            'action'        => 'Action',
            'role_id'       => 'Role ID',
            'user_id'       => 'User ID',
            'permission_id' => 'Perm ID',
            'old'           => 'Old',
            'new'           => 'New',
            'details'       => 'Details',
        ],
    ],

    'docs' => [
        'title'      => 'Documentation',
        'no_content' => 'No documentation available for this locale.',
    ],

    'action' => [
        'view'   => 'View',
        'create' => 'Create',
        'update' => 'Update',
        'edit'   => 'Edit',
        'delete' => 'Delete',
        'export' => 'Export',
        'import' => 'Import',
        'bulk'   => 'Bulk',
    ],
];
