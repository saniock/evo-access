# saniock/evo-access

Role and permission management for Evolution CMS 3 custom modules.

A centralised RBAC layer that replaces ad-hoc per-module `managerAccess`
hardcoding, `$_SESSION['mgrRole']` checks, and hand-edited
`plugin.ManagerAccess.php` files with a single, self-contained,
auditable system.

## Features

- **Per-permission action sets.** Each permission declares its own set
  of supported actions (`view`, `create`, `update`, `delete`, `export`,
  ...); host modules can add module-specific actions like `refund`,
  `approve`, `publish`.
- **Flat roles with cloning.** No hierarchy — superadmins create,
  clone, rename, and delete roles from the admin UI.
- **One role per user** (flat model), with **per-user overrides**
  in both directions: grant extra access or revoke specific actions.
- **Superadmin short-circuit.** One system role bypasses the matrix
  entirely to prevent accidental self-lockout.
- **Audit log.** Every grant/revoke/role change is recorded with
  actor, target, timestamp, and structured details.
- **Decoupled from EVO native roles.** Uses its own `ea_*` tables;
  the EVO `user_attributes.role` column is left alone and only
  consulted as a login gate.

## Installation (development, via path repo)

From the host Ddaudio project:

```json
// core/custom/composer.json
"repositories": [
    {
        "type": "path",
        "url": "packages/evo-access",
        "options": {"symlink": true}
    }
],
"require": {
    "saniock/evo-access": "@dev"
}
```

Then:

```bash
cd core/custom
composer require saniock/evo-access:@dev
php artisan vendor:publish --tag=evo-access-config
php artisan migrate
```

Edit `config/evoAccess.php` and add your EVO user IDs to
`bootstrap_superadmin_user_ids`, then:

```bash
php artisan evoaccess:bootstrap        # seeds superadmin role + assigns users
php artisan evoaccess:sync-permissions # syncs registered permission catalog to DB
```

Open `/access/matrix` in the manager to verify everything loaded.

## Quick start (consumer project)

Register the permissions exposed by each of your modules in your own
service provider's `boot()` method:

```php
use Saniock\EvoAccess\Facades\EvoAccess;

EvoAccess::registerPermissions('orders', [
    ['name' => 'orders.orders',
     'label' => 'Order list',
     'actions' => ['view', 'create', 'update', 'delete', 'refund', 'export']],
    ['name' => 'orders.payments',
     'label' => 'Payments',
     'actions' => ['view', 'update', 'refund']],
    // ...
]);
```

Then run `php artisan evoaccess:sync-permissions` to persist the
catalog, and use the admin UI (`/access/matrix`) to assign
permissions to roles.

In your controllers:

```php
use Saniock\EvoAccess\Facades\EvoAccess;

if (!EvoAccess::can('orders.orders', 'refund', $userId)) {
    abort(403);
}
```

## License

GPL-3.0-or-later
