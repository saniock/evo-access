# saniock/evo-access — Package Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement the saniock/evo-access composer package — a database-backed RBAC layer for Evolution CMS 3 custom modules — turning the existing scaffold of stubs into a fully working, locally-installable package with migrations, services, observers, console commands, and admin UI.

**Architecture:** Six MySQL tables (`ea_*`) hold roles, permissions, grants, user assignments, overrides, and audit log. Four singleton services (`AccessService`, `PermissionCatalog`, `PermissionResolver`, `AuditLogger`) implement the public API. Four Eloquent observers wire mutations into audit logging and per-request cache invalidation. An admin UI under `/access/` lets a superadmin manage everything. Full design lives in `docs/design.md`.

**Tech Stack:** PHP 8.3+, Laravel components (via EVO 3.5), MySQL 8 / MariaDB 10.x, Bootstrap 5 + vanilla JS for the package's reference admin UI (MIT, no caascade), PHPUnit 11, Orchestra Testbench for Laravel-package testing. Consumer projects override views via `vendor:publish` to plug in their own UI framework (Webix Pro, Tabler, etc.).

**Reference document:** [`docs/design.md`](./design.md) — read this first for full context.

**Scope of this plan:** Phase A only (the package itself). Phase B (Ddaudio consumer migration) is a separate plan written after Phase A is complete and tested.

---

## File structure overview

This plan implements the following files. Files marked **(stub exists)** already have skeleton content from the initial scaffold; the plan replaces stubs with real implementations. Files marked **(new)** must be created from scratch.

### Database

- **(new)** `src/Database/Migrations/2026_04_08_000001_create_ea_roles_table.php` — schema + superadmin seed
- **(new)** `src/Database/Migrations/2026_04_08_000002_create_ea_permissions_table.php`
- **(new)** `src/Database/Migrations/2026_04_08_000003_create_ea_role_permission_actions_table.php`
- **(new)** `src/Database/Migrations/2026_04_08_000004_create_ea_user_roles_table.php`
- **(new)** `src/Database/Migrations/2026_04_08_000005_create_ea_user_overrides_table.php`
- **(new)** `src/Database/Migrations/2026_04_08_000006_create_ea_audit_log_table.php`

### Models

- **(stub exists)** `src/Models/Role.php`
- **(stub exists)** `src/Models/Permission.php`
- **(stub exists)** `src/Models/RolePermissionAction.php`
- **(stub exists)** `src/Models/UserRole.php`
- **(stub exists)** `src/Models/UserOverride.php`
- **(stub exists)** `src/Models/AuditLog.php`

### Services

- **(stub exists)** `src/Services/PermissionCatalog.php` — registry with validation + sync
- **(stub exists)** `src/Services/PermissionResolver.php` — 16-case truth table resolver with cache
- **(stub exists)** `src/Services/AccessService.php` — public API + menu helpers
- **(stub exists)** `src/Services/AuditLogger.php` — audit writer + read API

### Observers

- **(new)** `src/Observers/RoleObserver.php`
- **(new)** `src/Observers/RolePermissionActionObserver.php`
- **(new)** `src/Observers/UserRoleObserver.php`
- **(new)** `src/Observers/UserOverrideObserver.php`

### Console commands

- **(new)** `src/Console/BootstrapCommand.php` — assigns config superadmin user IDs
- **(stub exists)** `src/Console/SyncPermissionsCommand.php` — already mostly complete, needs final wiring
- **(new)** `src/Console/MigrateLegacyEvoRolesCommand.php` — JSON-mapping migration helper

### HTTP layer

- **(stub exists)** `src/Http/routes.php` — defines route group, currently empty
- **(new)** `src/Controllers/BaseController.php` — auth gate for admin UI
- **(new)** `src/Controllers/RolesController.php` — CRUD for roles
- **(new)** `src/Controllers/MatrixController.php` — grant/revoke endpoints
- **(new)** `src/Controllers/UsersController.php` — user search + override management
- **(new)** `src/Controllers/AuditController.php` — audit log viewer

### Views (admin UI)

- **(new)** `views/layout.blade.php` — wrapper layout with menu
- **(new)** `views/roles.blade.php` — role list datatable
- **(new)** `views/matrix.blade.php` — main grant matrix
- **(new)** `views/users.blade.php` — user search + override panel
- **(new)** `views/audit.blade.php` — audit feed

### Tests

- **(new)** `phpunit.xml` — test config
- **(new)** `tests/TestCase.php` — base test case with Testbench
- **(new)** `tests/Unit/PermissionCatalogTest.php`
- **(new)** `tests/Unit/PermissionResolverTest.php`
- **(new)** `tests/Unit/AccessServiceTest.php`
- **(new)** `tests/Unit/AuditLoggerTest.php`
- **(new)** `tests/Feature/MigrationsTest.php`
- **(new)** `tests/Feature/ObserverTest.php`
- **(new)** `tests/Feature/SyncPermissionsCommandTest.php`
- **(new)** `tests/Feature/BootstrapCommandTest.php`

### Other

- **(stub exists)** `src/EvoAccessServiceProvider.php` — needs observer registration + final wiring
- **(stub exists)** `plugins/evoAccessPlugin.php` — needs real EVO menu inject

---

## Phase 0: Test infrastructure

This phase sets up PHPUnit + Orchestra Testbench so we can write tests for the rest of the package using Laravel's testing utilities. Tests run against an in-memory SQLite database for speed.

### Task 0.1: Add Orchestra Testbench dev dependency

**Files:**
- Modify: `composer.json`

- [ ] **Step 1: Add the dependency**

Edit `composer.json` to add `orchestra/testbench` to `require-dev`:

```json
"require-dev": {
    "phpunit/phpunit": "^11.0",
    "orchestra/testbench": "^9.0"
}
```

- [ ] **Step 2: Install**

Run from inside `core/custom/packages/evo-access/`:

```bash
composer install
```

Expected: `orchestra/testbench` and its dependencies appear in `vendor/`.

- [ ] **Step 3: Commit**

```bash
git add composer.json composer.lock
git commit -m "test(infra): add orchestra/testbench for package tests"
```

### Task 0.2: Create phpunit.xml + base TestCase

**Files:**
- Create: `phpunit.xml`
- Create: `tests/TestCase.php`

- [ ] **Step 1: Create phpunit.xml**

Create `phpunit.xml` in the package root:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
         bootstrap="vendor/autoload.php"
         colors="true"
         cacheDirectory=".phpunit.cache"
         executionOrder="random"
         resolveDependencies="true">
    <testsuites>
        <testsuite name="Unit">
            <directory suffix="Test.php">tests/Unit</directory>
        </testsuite>
        <testsuite name="Feature">
            <directory suffix="Test.php">tests/Feature</directory>
        </testsuite>
    </testsuites>
    <source>
        <include>
            <directory suffix=".php">src</directory>
        </include>
    </source>
    <php>
        <env name="DB_CONNECTION" value="testing"/>
        <env name="DB_DATABASE" value=":memory:"/>
    </php>
</phpunit>
```

- [ ] **Step 2: Create base TestCase**

Create `tests/TestCase.php`:

```php
<?php

namespace Saniock\EvoAccess\Tests;

use Orchestra\Testbench\TestCase as OrchestraTestCase;
use Saniock\EvoAccess\EvoAccessServiceProvider;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            EvoAccessServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../src/Database/Migrations');
    }
}
```

- [ ] **Step 3: Create empty test directories**

```bash
mkdir -p tests/Unit tests/Feature
touch tests/Unit/.gitkeep tests/Feature/.gitkeep
```

- [ ] **Step 4: Verify phpunit runs (with no tests yet)**

```bash
./vendor/bin/phpunit --list-tests
```

Expected: `No tests found.` (zero exit code; the framework loads OK)

- [ ] **Step 5: Commit**

```bash
git add phpunit.xml tests/
git commit -m "test(infra): add phpunit.xml + base TestCase with Testbench"
```

### Task 0.3: Smoke test that confirms test infrastructure works

**Files:**
- Create: `tests/Unit/SmokeTest.php`

- [ ] **Step 1: Write a trivial smoke test**

```php
<?php

namespace Saniock\EvoAccess\Tests\Unit;

use Saniock\EvoAccess\Tests\TestCase;

class SmokeTest extends TestCase
{
    public function test_service_provider_loads(): void
    {
        $this->assertTrue($this->app->providerIsLoaded(\Saniock\EvoAccess\EvoAccessServiceProvider::class));
    }

    public function test_facade_resolves(): void
    {
        $service = $this->app->make(\Saniock\EvoAccess\Services\AccessService::class);
        $this->assertInstanceOf(\Saniock\EvoAccess\Services\AccessService::class, $service);
    }
}
```

- [ ] **Step 2: Run smoke test**

```bash
./vendor/bin/phpunit tests/Unit/SmokeTest.php
```

Expected: `OK (2 tests, 2 assertions)`

- [ ] **Step 3: Commit**

```bash
git add tests/Unit/SmokeTest.php
git commit -m "test(infra): add smoke test for package boot"
```

---

## Phase 1: Database migrations

Six migration files in dependency order. Each migration creates one table per the DDL in `docs/design.md` §5. Migration 1 also seeds the system superadmin role.

### Task 1.1: Migration — `ea_roles` table + superadmin seed

**Files:**
- Create: `src/Database/Migrations/2026_04_08_000001_create_ea_roles_table.php`
- Create: `tests/Feature/MigrationsTest.php`

- [ ] **Step 1: Write the migration test first**

Create `tests/Feature/MigrationsTest.php`:

```php
<?php

namespace Saniock\EvoAccess\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Saniock\EvoAccess\Tests\TestCase;

class MigrationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_ea_roles_table_exists_with_required_columns(): void
    {
        $this->assertTrue(Schema::hasTable('ea_roles'));
        $this->assertTrue(Schema::hasColumns('ea_roles', [
            'id', 'name', 'label', 'description', 'is_system',
            'created_by', 'created_at', 'updated_at',
        ]));
    }

    public function test_superadmin_role_is_seeded(): void
    {
        $row = DB::table('ea_roles')->where('name', 'superadmin')->first();

        $this->assertNotNull($row);
        $this->assertSame(1, (int) $row->is_system);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
./vendor/bin/phpunit tests/Feature/MigrationsTest.php
```

Expected: 2 failures — `Failed asserting that false is true` because the table doesn't exist yet.

- [ ] **Step 3: Create the migration**

Create `src/Database/Migrations/2026_04_08_000001_create_ea_roles_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ea_roles', function (Blueprint $table) {
            $table->unsignedInteger('id')->autoIncrement();
            $table->string('name', 64);
            $table->string('label', 128);
            $table->string('description', 255)->nullable();
            $table->boolean('is_system')->default(false);
            $table->unsignedInteger('created_by')->nullable();
            $table->timestamps();

            $table->unique('name', 'ea_roles_name_unique');
            $table->index('is_system', 'ea_roles_is_system_idx');
        });

        DB::table('ea_roles')->insert([
            'name'        => 'superadmin',
            'label'       => 'Суперадмін',
            'description' => 'System role with unconditional full access',
            'is_system'   => 1,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('ea_roles');
    }
};
```

- [ ] **Step 4: Run test to verify it passes**

```bash
./vendor/bin/phpunit tests/Feature/MigrationsTest.php
```

Expected: `OK (2 tests, 3 assertions)`

- [ ] **Step 5: Commit**

```bash
git add src/Database/Migrations/2026_04_08_000001_create_ea_roles_table.php tests/Feature/MigrationsTest.php
git commit -m "feat(migrations): create ea_roles table + seed superadmin role"
```

### Task 1.2: Migration — `ea_permissions` table

**Files:**
- Create: `src/Database/Migrations/2026_04_08_000002_create_ea_permissions_table.php`
- Modify: `tests/Feature/MigrationsTest.php`

- [ ] **Step 1: Add a test for the new table**

Append to `tests/Feature/MigrationsTest.php`:

```php
public function test_ea_permissions_table_exists_with_required_columns(): void
{
    $this->assertTrue(Schema::hasTable('ea_permissions'));
    $this->assertTrue(Schema::hasColumns('ea_permissions', [
        'id', 'name', 'label', 'module', 'actions',
        'is_orphaned', 'created_at', 'updated_at',
    ]));
}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
./vendor/bin/phpunit tests/Feature/MigrationsTest.php --filter test_ea_permissions
```

Expected: FAIL — table does not exist.

- [ ] **Step 3: Create the migration**

Create `src/Database/Migrations/2026_04_08_000002_create_ea_permissions_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ea_permissions', function (Blueprint $table) {
            $table->unsignedInteger('id')->autoIncrement();
            $table->string('name', 128);
            $table->string('label', 255);
            $table->string('module', 64);
            $table->json('actions');
            $table->boolean('is_orphaned')->default(false);
            $table->timestamps();

            $table->unique('name', 'ea_permissions_name_unique');
            $table->index('module', 'ea_permissions_module_idx');
            $table->index('is_orphaned', 'ea_permissions_orphaned_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ea_permissions');
    }
};
```

- [ ] **Step 4: Run test**

```bash
./vendor/bin/phpunit tests/Feature/MigrationsTest.php --filter test_ea_permissions
```

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Database/Migrations/2026_04_08_000002_create_ea_permissions_table.php tests/Feature/MigrationsTest.php
git commit -m "feat(migrations): create ea_permissions table"
```

### Task 1.3: Migration — `ea_role_permission_actions` table

**Files:**
- Create: `src/Database/Migrations/2026_04_08_000003_create_ea_role_permission_actions_table.php`
- Modify: `tests/Feature/MigrationsTest.php`

- [ ] **Step 1: Add a test**

Append to `tests/Feature/MigrationsTest.php`:

```php
public function test_ea_role_permission_actions_table_exists_with_composite_pk(): void
{
    $this->assertTrue(Schema::hasTable('ea_role_permission_actions'));
    $this->assertTrue(Schema::hasColumns('ea_role_permission_actions', [
        'role_id', 'permission_id', 'action', 'granted_by', 'granted_at',
    ]));
}
```

- [ ] **Step 2: Run test, expect failure**

```bash
./vendor/bin/phpunit tests/Feature/MigrationsTest.php --filter test_ea_role_permission_actions
```

- [ ] **Step 3: Create the migration**

Create `src/Database/Migrations/2026_04_08_000003_create_ea_role_permission_actions_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ea_role_permission_actions', function (Blueprint $table) {
            $table->unsignedInteger('role_id');
            $table->unsignedInteger('permission_id');
            $table->string('action', 32);
            $table->unsignedInteger('granted_by')->nullable();
            $table->timestamp('granted_at')->useCurrent();

            $table->primary(['role_id', 'permission_id', 'action']);
            $table->index('permission_id', 'ea_rpa_permission_idx');

            $table->foreign('role_id', 'ea_rpa_role_fk')
                ->references('id')->on('ea_roles')
                ->cascadeOnDelete();

            $table->foreign('permission_id', 'ea_rpa_permission_fk')
                ->references('id')->on('ea_permissions')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ea_role_permission_actions');
    }
};
```

- [ ] **Step 4: Run test, expect pass**

```bash
./vendor/bin/phpunit tests/Feature/MigrationsTest.php --filter test_ea_role_permission_actions
```

- [ ] **Step 5: Commit**

```bash
git add src/Database/Migrations/2026_04_08_000003_create_ea_role_permission_actions_table.php tests/Feature/MigrationsTest.php
git commit -m "feat(migrations): create ea_role_permission_actions join table"
```

### Task 1.4: Migration — `ea_user_roles` table

**Files:**
- Create: `src/Database/Migrations/2026_04_08_000004_create_ea_user_roles_table.php`
- Modify: `tests/Feature/MigrationsTest.php`

- [ ] **Step 1: Add a test**

Append to `tests/Feature/MigrationsTest.php`:

```php
public function test_ea_user_roles_table_exists_with_user_id_pk(): void
{
    $this->assertTrue(Schema::hasTable('ea_user_roles'));
    $this->assertTrue(Schema::hasColumns('ea_user_roles', [
        'user_id', 'role_id', 'assigned_by', 'assigned_at',
    ]));
}
```

- [ ] **Step 2: Run test, expect failure**

```bash
./vendor/bin/phpunit tests/Feature/MigrationsTest.php --filter test_ea_user_roles
```

- [ ] **Step 3: Create the migration**

Create `src/Database/Migrations/2026_04_08_000004_create_ea_user_roles_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ea_user_roles', function (Blueprint $table) {
            $table->unsignedInteger('user_id')->primary();
            $table->unsignedInteger('role_id');
            $table->unsignedInteger('assigned_by')->nullable();
            $table->timestamp('assigned_at')->useCurrent();

            $table->index('role_id', 'ea_user_roles_role_idx');

            $table->foreign('role_id', 'ea_user_roles_role_fk')
                ->references('id')->on('ea_roles')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ea_user_roles');
    }
};
```

- [ ] **Step 4: Run test**

```bash
./vendor/bin/phpunit tests/Feature/MigrationsTest.php --filter test_ea_user_roles
```

- [ ] **Step 5: Commit**

```bash
git add src/Database/Migrations/2026_04_08_000004_create_ea_user_roles_table.php tests/Feature/MigrationsTest.php
git commit -m "feat(migrations): create ea_user_roles assignment table"
```

### Task 1.5: Migration — `ea_user_overrides` table

**Files:**
- Create: `src/Database/Migrations/2026_04_08_000005_create_ea_user_overrides_table.php`
- Modify: `tests/Feature/MigrationsTest.php`

- [ ] **Step 1: Add a test**

Append to `tests/Feature/MigrationsTest.php`:

```php
public function test_ea_user_overrides_table_exists_with_mode_column(): void
{
    $this->assertTrue(Schema::hasTable('ea_user_overrides'));
    $this->assertTrue(Schema::hasColumns('ea_user_overrides', [
        'user_id', 'permission_id', 'action', 'mode',
        'reason', 'created_by', 'created_at',
    ]));
}
```

- [ ] **Step 2: Run test, expect failure**

```bash
./vendor/bin/phpunit tests/Feature/MigrationsTest.php --filter test_ea_user_overrides
```

- [ ] **Step 3: Create the migration**

Create `src/Database/Migrations/2026_04_08_000005_create_ea_user_overrides_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ea_user_overrides', function (Blueprint $table) {
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('permission_id');
            $table->string('action', 32);
            $table->enum('mode', ['grant', 'revoke']);
            $table->string('reason', 255)->nullable();
            $table->unsignedInteger('created_by')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->primary(['user_id', 'permission_id', 'action']);
            $table->index('user_id', 'ea_uo_user_idx');
            $table->index('permission_id', 'ea_uo_permission_idx');

            $table->foreign('permission_id', 'ea_uo_permission_fk')
                ->references('id')->on('ea_permissions')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ea_user_overrides');
    }
};
```

- [ ] **Step 4: Run test**

```bash
./vendor/bin/phpunit tests/Feature/MigrationsTest.php --filter test_ea_user_overrides
```

- [ ] **Step 5: Commit**

```bash
git add src/Database/Migrations/2026_04_08_000005_create_ea_user_overrides_table.php tests/Feature/MigrationsTest.php
git commit -m "feat(migrations): create ea_user_overrides table with grant/revoke mode"
```

### Task 1.6: Migration — `ea_audit_log` table

**Files:**
- Create: `src/Database/Migrations/2026_04_08_000006_create_ea_audit_log_table.php`
- Modify: `tests/Feature/MigrationsTest.php`

- [ ] **Step 1: Add a test**

Append to `tests/Feature/MigrationsTest.php`:

```php
public function test_ea_audit_log_table_exists_with_no_foreign_keys(): void
{
    $this->assertTrue(Schema::hasTable('ea_audit_log'));
    $this->assertTrue(Schema::hasColumns('ea_audit_log', [
        'id', 'actor_user_id', 'action', 'target_role_id', 'target_user_id',
        'permission_id', 'old_value', 'new_value', 'details', 'created_at',
    ]));
}
```

- [ ] **Step 2: Run test, expect failure**

```bash
./vendor/bin/phpunit tests/Feature/MigrationsTest.php --filter test_ea_audit_log
```

- [ ] **Step 3: Create the migration**

Create `src/Database/Migrations/2026_04_08_000006_create_ea_audit_log_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ea_audit_log', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->autoIncrement();
            $table->unsignedInteger('actor_user_id');
            $table->string('action', 32);
            $table->unsignedInteger('target_role_id')->nullable();
            $table->unsignedInteger('target_user_id')->nullable();
            $table->unsignedInteger('permission_id')->nullable();
            $table->string('old_value', 255)->nullable();
            $table->string('new_value', 255)->nullable();
            $table->json('details')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['actor_user_id', 'created_at'], 'ea_audit_actor_idx');
            $table->index(['target_role_id', 'created_at'], 'ea_audit_role_idx');
            $table->index(['target_user_id', 'created_at'], 'ea_audit_user_idx');
            $table->index('action', 'ea_audit_action_idx');
            $table->index('created_at', 'ea_audit_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ea_audit_log');
    }
};
```

- [ ] **Step 4: Run all migration tests**

```bash
./vendor/bin/phpunit tests/Feature/MigrationsTest.php
```

Expected: All tests pass — 7 tests, 13 assertions.

- [ ] **Step 5: Commit**

```bash
git add src/Database/Migrations/2026_04_08_000006_create_ea_audit_log_table.php tests/Feature/MigrationsTest.php
git commit -m "feat(migrations): create ea_audit_log table with no foreign keys"
```

---

## Phase 2: Eloquent models

The models already have stubs from the scaffold. This phase replaces stubs with full bodies including relationships, query scopes where useful, and tests verifying basic CRUD + casts.

### Task 2.1: `Role` model — relationships + tests

**Files:**
- Modify: `src/Models/Role.php`
- Create: `tests/Unit/Models/RoleTest.php`

- [ ] **Step 1: Write test for Role basic CRUD**

Create `tests/Unit/Models/RoleTest.php`:

```php
<?php

namespace Saniock\EvoAccess\Tests\Unit\Models;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Saniock\EvoAccess\Models\Role;
use Saniock\EvoAccess\Tests\TestCase;

class RoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_a_role(): void
    {
        $role = Role::create([
            'name'        => 'manager',
            'label'       => 'Менеджер',
            'description' => 'Тестова роль',
        ]);

        $this->assertNotNull($role->id);
        $this->assertSame('manager', $role->name);
        $this->assertFalse($role->is_system);
    }

    public function test_superadmin_role_is_seeded_and_flagged(): void
    {
        $superadmin = Role::where('name', 'superadmin')->firstOrFail();
        $this->assertTrue($superadmin->is_system);
    }

    public function test_grants_relationship(): void
    {
        $role = Role::create(['name' => 'r1', 'label' => 'R1']);
        $this->assertCount(0, $role->grants);
    }
}
```

- [ ] **Step 2: Run test, expect failure**

```bash
./vendor/bin/phpunit tests/Unit/Models/RoleTest.php
```

Expected: failures around `$role->grants` (relationship not defined).

- [ ] **Step 3: Replace `src/Models/Role.php` with full body**

```php
<?php

namespace Saniock\EvoAccess\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Access-control role: a named bundle of permission grants.
 *
 * @property int    $id
 * @property string $name
 * @property string $label
 * @property string|null $description
 * @property bool   $is_system
 * @property int|null $created_by
 */
class Role extends Model
{
    protected $table = 'ea_roles';

    protected $fillable = [
        'name',
        'label',
        'description',
        'is_system',
        'created_by',
    ];

    protected $casts = [
        'is_system' => 'bool',
    ];

    public function grants(): HasMany
    {
        return $this->hasMany(RolePermissionAction::class, 'role_id');
    }

    public function userAssignments(): HasMany
    {
        return $this->hasMany(UserRole::class, 'role_id');
    }

    public function scopeSystem($query)
    {
        return $query->where('is_system', true);
    }
}
```

- [ ] **Step 4: Run test, expect pass**

```bash
./vendor/bin/phpunit tests/Unit/Models/RoleTest.php
```

Expected: `OK (3 tests, 5 assertions)`

- [ ] **Step 5: Commit**

```bash
git add src/Models/Role.php tests/Unit/Models/RoleTest.php
git commit -m "feat(models): expand Role with relationships + tests"
```

### Task 2.2: `Permission` model — JSON cast + tests

**Files:**
- Modify: `src/Models/Permission.php`
- Create: `tests/Unit/Models/PermissionTest.php`

- [ ] **Step 1: Write test**

Create `tests/Unit/Models/PermissionTest.php`:

```php
<?php

namespace Saniock\EvoAccess\Tests\Unit\Models;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Saniock\EvoAccess\Models\Permission;
use Saniock\EvoAccess\Tests\TestCase;

class PermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_actions_are_cast_to_array(): void
    {
        $perm = Permission::create([
            'name'    => 'orders.orders',
            'label'   => 'Order list',
            'module'  => 'orders',
            'actions' => ['view', 'update', 'export'],
        ]);

        $perm->refresh();

        $this->assertIsArray($perm->actions);
        $this->assertSame(['view', 'update', 'export'], $perm->actions);
    }

    public function test_is_orphaned_defaults_to_false(): void
    {
        $perm = Permission::create([
            'name'    => 'orders.payments',
            'label'   => 'Payments',
            'module'  => 'orders',
            'actions' => ['view'],
        ]);

        $this->assertFalse($perm->is_orphaned);
    }

    public function test_only_active_scope(): void
    {
        Permission::create([
            'name' => 'a.x', 'label' => 'A', 'module' => 'a', 'actions' => ['view'],
            'is_orphaned' => false,
        ]);
        Permission::create([
            'name' => 'a.y', 'label' => 'B', 'module' => 'a', 'actions' => ['view'],
            'is_orphaned' => true,
        ]);

        $this->assertCount(1, Permission::active()->get());
    }
}
```

- [ ] **Step 2: Run test, expect failure**

```bash
./vendor/bin/phpunit tests/Unit/Models/PermissionTest.php
```

- [ ] **Step 3: Replace `src/Models/Permission.php` with full body**

```php
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
```

- [ ] **Step 4: Run test**

```bash
./vendor/bin/phpunit tests/Unit/Models/PermissionTest.php
```

Expected: `OK (3 tests, 4 assertions)`

- [ ] **Step 5: Commit**

```bash
git add src/Models/Permission.php tests/Unit/Models/PermissionTest.php
git commit -m "feat(models): expand Permission with active scope + tests"
```

### Task 2.3: Remaining models — `RolePermissionAction`, `UserRole`, `UserOverride`, `AuditLog`

These four models follow nearly identical patterns. The existing stubs from the scaffold are mostly correct — this task verifies them with tests and adds any missing scopes.

**Files:**
- Modify (verify): `src/Models/RolePermissionAction.php`
- Modify (verify): `src/Models/UserRole.php`
- Modify (verify): `src/Models/UserOverride.php`
- Modify (verify): `src/Models/AuditLog.php`
- Create: `tests/Unit/Models/MiscModelsTest.php`

- [ ] **Step 1: Write tests for all four models**

Create `tests/Unit/Models/MiscModelsTest.php`:

```php
<?php

namespace Saniock\EvoAccess\Tests\Unit\Models;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Saniock\EvoAccess\Models\AuditLog;
use Saniock\EvoAccess\Models\Permission;
use Saniock\EvoAccess\Models\Role;
use Saniock\EvoAccess\Models\RolePermissionAction;
use Saniock\EvoAccess\Models\UserOverride;
use Saniock\EvoAccess\Models\UserRole;
use Saniock\EvoAccess\Tests\TestCase;

class MiscModelsTest extends TestCase
{
    use RefreshDatabase;

    private function makePermission(string $name = 'orders.orders'): Permission
    {
        return Permission::create([
            'name'    => $name,
            'label'   => 'L',
            'module'  => 'orders',
            'actions' => ['view', 'update'],
        ]);
    }

    public function test_role_permission_action_uses_composite_pk(): void
    {
        $role = Role::create(['name' => 'r1', 'label' => 'R1']);
        $perm = $this->makePermission();

        RolePermissionAction::create([
            'role_id'       => $role->id,
            'permission_id' => $perm->id,
            'action'        => 'view',
        ]);

        $this->assertSame(1, RolePermissionAction::where('role_id', $role->id)->count());
    }

    public function test_user_role_one_per_user(): void
    {
        $role = Role::where('name', 'superadmin')->firstOrFail();

        UserRole::create([
            'user_id' => 7,
            'role_id' => $role->id,
        ]);

        $row = UserRole::where('user_id', 7)->first();
        $this->assertSame($role->id, $row->role_id);
    }

    public function test_user_override_grant_mode(): void
    {
        $perm = $this->makePermission();

        UserOverride::create([
            'user_id'       => 42,
            'permission_id' => $perm->id,
            'action'        => 'export',
            'mode'          => 'grant',
            'reason'        => 'Test',
        ]);

        $row = UserOverride::where('user_id', 42)->first();
        $this->assertSame('grant', $row->mode);
    }

    public function test_audit_log_records_with_json_details(): void
    {
        AuditLog::create([
            'actor_user_id' => 7,
            'action'        => 'create_role',
            'target_role_id'=> 1,
            'details'       => ['old' => null, 'new' => 'manager'],
        ]);

        $row = AuditLog::first();
        $this->assertIsArray($row->details);
        $this->assertSame('manager', $row->details['new']);
    }
}
```

- [ ] **Step 2: Run tests, fix any failing assertions in existing stubs**

```bash
./vendor/bin/phpunit tests/Unit/Models/MiscModelsTest.php
```

Most existing stubs already have the right `$table`, `$casts`, `$fillable`. If anything fails, fix the corresponding model file.

- [ ] **Step 3: Add `cancelTimestamps` and remove default `id` PK from join models**

Verify these properties on each composite-PK model:

For `RolePermissionAction`:
```php
public $incrementing = false;
public $timestamps = false;
protected $primaryKey = null;
```

For `UserOverride`:
```php
public $incrementing = false;
public const UPDATED_AT = null;
```

For `AuditLog`:
```php
public const UPDATED_AT = null;
```

If any of these are missing in the stubs, add them.

- [ ] **Step 4: Run tests again**

```bash
./vendor/bin/phpunit tests/Unit/Models/
```

Expected: All model tests pass.

- [ ] **Step 5: Commit**

```bash
git add src/Models/ tests/Unit/Models/MiscModelsTest.php
git commit -m "feat(models): finalize join-table models with composite-PK behaviour + tests"
```

---

## Phase 3: PermissionCatalog implementation

The catalog is the simplest service — pure in-memory data structure with validation and one DB write method (`syncToDatabase`).

### Task 3.1: `registerPermissions` with full validation

**Files:**
- Modify: `src/Services/PermissionCatalog.php`
- Create: `tests/Unit/Services/PermissionCatalogTest.php`

- [ ] **Step 1: Write tests for registration + validation**

Create `tests/Unit/Services/PermissionCatalogTest.php`:

```php
<?php

namespace Saniock\EvoAccess\Tests\Unit\Services;

use InvalidArgumentException;
use Saniock\EvoAccess\Services\PermissionCatalog;
use Saniock\EvoAccess\Tests\TestCase;

class PermissionCatalogTest extends TestCase
{
    public function test_can_register_a_valid_batch(): void
    {
        $catalog = new PermissionCatalog();

        $catalog->registerPermissions('orders', [
            ['name' => 'orders.orders',   'label' => 'Order list', 'actions' => ['view', 'update']],
            ['name' => 'orders.payments', 'label' => 'Payments',   'actions' => ['view']],
        ]);

        $this->assertCount(2, $catalog->all());
    }

    public function test_validates_module_slug_format(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/module slug/i');

        (new PermissionCatalog())->registerPermissions('Orders', [
            ['name' => 'orders.x', 'label' => 'X', 'actions' => ['view']],
        ]);
    }

    public function test_validates_name_format(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches("/Permission name must match/");

        (new PermissionCatalog())->registerPermissions('orders', [
            ['name' => 'NoDot', 'label' => 'X', 'actions' => ['view']],
        ]);
    }

    public function test_validates_name_starts_with_module_prefix(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches("/must start with module slug/");

        (new PermissionCatalog())->registerPermissions('orders', [
            ['name' => 'finances.x', 'label' => 'X', 'actions' => ['view']],
        ]);
    }

    public function test_validates_label_required(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new PermissionCatalog())->registerPermissions('orders', [
            ['name' => 'orders.x', 'label' => '', 'actions' => ['view']],
        ]);
    }

    public function test_validates_actions_non_empty(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new PermissionCatalog())->registerPermissions('orders', [
            ['name' => 'orders.x', 'label' => 'X', 'actions' => []],
        ]);
    }

    public function test_validates_action_format(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new PermissionCatalog())->registerPermissions('orders', [
            ['name' => 'orders.x', 'label' => 'X', 'actions' => ['VIEW']],
        ]);
    }

    public function test_validates_no_duplicate_actions(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/duplicate action/');

        (new PermissionCatalog())->registerPermissions('orders', [
            ['name' => 'orders.x', 'label' => 'X', 'actions' => ['view', 'view']],
        ]);
    }

    public function test_duplicate_name_overwrites_with_warning(): void
    {
        $catalog = new PermissionCatalog();

        $catalog->registerPermissions('orders', [
            ['name' => 'orders.x', 'label' => 'A', 'actions' => ['view']],
        ]);

        $catalog->registerPermissions('orders', [
            ['name' => 'orders.x', 'label' => 'B', 'actions' => ['view', 'update']],
        ]);

        $found = $catalog->find('orders.x');
        $this->assertSame('B', $found['label']);
        $this->assertSame(['view', 'update'], $found['actions']);
    }
}
```

- [ ] **Step 2: Run tests, expect failures (no validation yet)**

```bash
./vendor/bin/phpunit tests/Unit/Services/PermissionCatalogTest.php
```

- [ ] **Step 3: Implement `registerPermissions` with validation**

Replace the `registerPermissions` method body in `src/Services/PermissionCatalog.php`:

```php
public function registerPermissions(string $module, array $permissions): void
{
    $this->validateModuleSlug($module);

    foreach ($permissions as $row) {
        $this->validatePermissionRow($module, $row);

        // Last-write-wins: remove any existing entry with the same name
        $this->permissions = array_values(array_filter(
            $this->permissions,
            fn($p) => $p['name'] !== $row['name']
        ));

        $this->permissions[] = [
            'name'    => $row['name'],
            'label'   => $row['label'],
            'module'  => $module,
            'actions' => array_values($row['actions']),
        ];
    }
}

private function validateModuleSlug(string $module): void
{
    if (!preg_match('/^[a-z][a-z0-9_]*$/', $module) || strlen($module) > 64) {
        throw new \InvalidArgumentException(
            "Invalid module slug '$module' — must match ^[a-z][a-z0-9_]*$ and be ≤64 chars"
        );
    }
}

private function validatePermissionRow(string $module, array $row): void
{
    $name = $row['name'] ?? '';
    if (!is_string($name) || $name === '' || strlen($name) > 128) {
        throw new \InvalidArgumentException("Permission name is required and must be ≤128 chars");
    }
    if (!preg_match('/^[a-z][a-z0-9_]*\.[a-z0-9_.]+$/', $name)) {
        throw new \InvalidArgumentException(
            "Permission name must match 'module.section[.subsection]' (got '$name')"
        );
    }
    if (!str_starts_with($name, $module . '.')) {
        throw new \InvalidArgumentException(
            "Permission '$name' must start with module slug '$module.'"
        );
    }

    $label = $row['label'] ?? '';
    if (!is_string($label) || $label === '' || strlen($label) > 255) {
        throw new \InvalidArgumentException(
            "Permission '$name' label is required and must be ≤255 chars"
        );
    }

    $actions = $row['actions'] ?? null;
    if (!is_array($actions) || empty($actions)) {
        throw new \InvalidArgumentException(
            "Permission '$name' must have at least one action"
        );
    }

    $seen = [];
    foreach ($actions as $action) {
        if (!is_string($action) || !preg_match('/^[a-z][a-z0-9_]*$/', $action) || strlen($action) > 32) {
            throw new \InvalidArgumentException(
                "Permission '$name' has invalid action '$action' (must be lowercase snake_case, ≤32 chars)"
            );
        }
        if (isset($seen[$action])) {
            throw new \InvalidArgumentException(
                "Permission '$name' has duplicate action '$action'"
            );
        }
        $seen[$action] = true;
    }
}
```

- [ ] **Step 4: Run tests**

```bash
./vendor/bin/phpunit tests/Unit/Services/PermissionCatalogTest.php
```

Expected: All 9 tests pass.

- [ ] **Step 5: Commit**

```bash
git add src/Services/PermissionCatalog.php tests/Unit/Services/PermissionCatalogTest.php
git commit -m "feat(catalog): registerPermissions with full validation + tests"
```

### Task 3.2: Catalog getters — `find`, `byModule`, `modules`

**Files:**
- Modify: `src/Services/PermissionCatalog.php`
- Modify: `tests/Unit/Services/PermissionCatalogTest.php`

- [ ] **Step 1: Add tests for getters**

Append to `tests/Unit/Services/PermissionCatalogTest.php`:

```php
public function test_find_returns_null_for_unknown(): void
{
    $catalog = new PermissionCatalog();
    $this->assertNull($catalog->find('nope.never'));
}

public function test_by_module_returns_only_module_rows(): void
{
    $catalog = new PermissionCatalog();
    $catalog->registerPermissions('orders', [
        ['name' => 'orders.x', 'label' => 'X', 'actions' => ['view']],
        ['name' => 'orders.y', 'label' => 'Y', 'actions' => ['view']],
    ]);
    $catalog->registerPermissions('finances', [
        ['name' => 'finances.z', 'label' => 'Z', 'actions' => ['view']],
    ]);

    $orders = $catalog->byModule('orders');
    $this->assertCount(2, $orders);
}

public function test_modules_returns_unique_sorted_list(): void
{
    $catalog = new PermissionCatalog();
    $catalog->registerPermissions('orders', [
        ['name' => 'orders.x', 'label' => 'X', 'actions' => ['view']],
    ]);
    $catalog->registerPermissions('finances', [
        ['name' => 'finances.x', 'label' => 'X', 'actions' => ['view']],
    ]);
    $catalog->registerPermissions('analytics', [
        ['name' => 'analytics.x', 'label' => 'X', 'actions' => ['view']],
    ]);

    $this->assertSame(['analytics', 'finances', 'orders'], $catalog->modules());
}
```

- [ ] **Step 2: Run tests, expect failures**

```bash
./vendor/bin/phpunit tests/Unit/Services/PermissionCatalogTest.php --filter "find|byModule|modules"
```

- [ ] **Step 3: Implement getters**

Replace the getter methods in `src/Services/PermissionCatalog.php`:

```php
public function all(): array
{
    return $this->permissions;
}

public function find(string $name): ?array
{
    foreach ($this->permissions as $row) {
        if ($row['name'] === $name) {
            return $row;
        }
    }
    return null;
}

public function byModule(string $module): array
{
    return array_values(array_filter(
        $this->permissions,
        fn($p) => $p['module'] === $module
    ));
}

public function modules(): array
{
    $modules = array_unique(array_column($this->permissions, 'module'));
    sort($modules);
    return array_values($modules);
}
```

- [ ] **Step 4: Run tests**

```bash
./vendor/bin/phpunit tests/Unit/Services/PermissionCatalogTest.php
```

- [ ] **Step 5: Commit**

```bash
git add src/Services/PermissionCatalog.php tests/Unit/Services/PermissionCatalogTest.php
git commit -m "feat(catalog): add find/byModule/modules getters + tests"
```

### Task 3.3: `syncToDatabase` — UPSERT + orphan flagging

**Files:**
- Modify: `src/Services/PermissionCatalog.php`
- Modify: `tests/Unit/Services/PermissionCatalogTest.php`

- [ ] **Step 1: Add sync test**

Append to `tests/Unit/Services/PermissionCatalogTest.php`:

```php
use Illuminate\Foundation\Testing\RefreshDatabase;
use Saniock\EvoAccess\Models\Permission;

// add this trait at the class level — already imported above
```

Add `use RefreshDatabase;` at the top of the test class, then add:

```php
public function test_sync_creates_new_permissions(): void
{
    $catalog = new PermissionCatalog();
    $catalog->registerPermissions('orders', [
        ['name' => 'orders.orders',   'label' => 'Order list', 'actions' => ['view', 'update']],
        ['name' => 'orders.payments', 'label' => 'Payments',   'actions' => ['view']],
    ]);

    $result = $catalog->syncToDatabase();

    $this->assertSame(2, $result['created']);
    $this->assertSame(0, $result['updated']);
    $this->assertSame(0, $result['orphaned']);
    $this->assertSame(2, Permission::count());
}

public function test_sync_updates_existing_permissions(): void
{
    Permission::create([
        'name'    => 'orders.orders',
        'label'   => 'Old label',
        'module'  => 'orders',
        'actions' => ['view'],
    ]);

    $catalog = new PermissionCatalog();
    $catalog->registerPermissions('orders', [
        ['name' => 'orders.orders', 'label' => 'New label', 'actions' => ['view', 'update']],
    ]);

    $result = $catalog->syncToDatabase();

    $this->assertSame(1, $result['updated']);
    $perm = Permission::where('name', 'orders.orders')->first();
    $this->assertSame('New label', $perm->label);
    $this->assertSame(['view', 'update'], $perm->actions);
}

public function test_sync_marks_orphans(): void
{
    Permission::create([
        'name'    => 'orders.removed',
        'label'   => 'Removed',
        'module'  => 'orders',
        'actions' => ['view'],
    ]);

    $catalog = new PermissionCatalog();
    $catalog->registerPermissions('orders', [
        ['name' => 'orders.orders', 'label' => 'Order list', 'actions' => ['view']],
    ]);

    $result = $catalog->syncToDatabase();

    $this->assertSame(1, $result['orphaned']);
    $orphaned = Permission::where('name', 'orders.removed')->first();
    $this->assertTrue($orphaned->is_orphaned);
}

public function test_sync_unflags_orphan_when_re_registered(): void
{
    Permission::create([
        'name'        => 'orders.x',
        'label'       => 'X',
        'module'      => 'orders',
        'actions'     => ['view'],
        'is_orphaned' => true,
    ]);

    $catalog = new PermissionCatalog();
    $catalog->registerPermissions('orders', [
        ['name' => 'orders.x', 'label' => 'X', 'actions' => ['view']],
    ]);

    $catalog->syncToDatabase();

    $perm = Permission::where('name', 'orders.x')->first();
    $this->assertFalse($perm->is_orphaned);
}
```

- [ ] **Step 2: Run tests, expect failures**

```bash
./vendor/bin/phpunit tests/Unit/Services/PermissionCatalogTest.php --filter sync
```

- [ ] **Step 3: Implement `syncToDatabase`**

Replace the method in `src/Services/PermissionCatalog.php`:

```php
public function syncToDatabase(): array
{
    return \DB::transaction(function () {
        $created = 0;
        $updated = 0;
        $orphaned = 0;

        $catalogNames = array_column($this->permissions, 'name');

        foreach ($this->permissions as $row) {
            $existing = \Saniock\EvoAccess\Models\Permission::where('name', $row['name'])->first();

            if ($existing) {
                $existing->fill([
                    'label'       => $row['label'],
                    'module'      => $row['module'],
                    'actions'     => $row['actions'],
                    'is_orphaned' => false,
                ]);

                if ($existing->isDirty()) {
                    $existing->save();
                    $updated++;
                }
            } else {
                \Saniock\EvoAccess\Models\Permission::create([
                    'name'        => $row['name'],
                    'label'       => $row['label'],
                    'module'      => $row['module'],
                    'actions'     => $row['actions'],
                    'is_orphaned' => false,
                ]);
                $created++;
            }
        }

        // Mark anything in DB but not in catalog as orphaned
        $orphaned = \Saniock\EvoAccess\Models\Permission::query()
            ->whereNotIn('name', $catalogNames)
            ->where('is_orphaned', false)
            ->update(['is_orphaned' => true]);

        return [
            'created'  => $created,
            'updated'  => $updated,
            'orphaned' => $orphaned,
        ];
    });
}
```

- [ ] **Step 4: Run tests**

```bash
./vendor/bin/phpunit tests/Unit/Services/PermissionCatalogTest.php
```

Expected: All ~13 tests pass.

- [ ] **Step 5: Commit**

```bash
git add src/Services/PermissionCatalog.php tests/Unit/Services/PermissionCatalogTest.php
git commit -m "feat(catalog): syncToDatabase with UPSERT + orphan flagging"
```

---

## Phase 4: PermissionResolver implementation

The resolver is the heart of the system. It implements the 16-case truth table from `docs/design.md` §7.

### Task 4.1: `roleOf` + `isSuperadmin` helpers

**Files:**
- Modify: `src/Services/PermissionResolver.php`
- Create: `tests/Unit/Services/PermissionResolverTest.php`

- [ ] **Step 1: Write tests**

Create `tests/Unit/Services/PermissionResolverTest.php`:

```php
<?php

namespace Saniock\EvoAccess\Tests\Unit\Services;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Saniock\EvoAccess\Models\Role;
use Saniock\EvoAccess\Models\UserRole;
use Saniock\EvoAccess\Services\PermissionResolver;
use Saniock\EvoAccess\Tests\TestCase;

class PermissionResolverTest extends TestCase
{
    use RefreshDatabase;

    private function resolver(): PermissionResolver
    {
        return $this->app->make(PermissionResolver::class);
    }

    public function test_role_of_returns_null_for_unassigned_user(): void
    {
        $this->assertNull($this->resolver()->roleOf(999));
    }

    public function test_role_of_returns_role_for_assigned_user(): void
    {
        $role = Role::create(['name' => 'manager', 'label' => 'M']);
        UserRole::create(['user_id' => 7, 'role_id' => $role->id]);

        $found = $this->resolver()->roleOf(7);
        $this->assertNotNull($found);
        $this->assertSame('manager', $found->name);
    }

    public function test_is_superadmin_true_for_system_role(): void
    {
        $superadmin = Role::where('name', 'superadmin')->firstOrFail();
        UserRole::create(['user_id' => 7, 'role_id' => $superadmin->id]);

        $this->assertTrue($this->resolver()->isSuperadmin(7));
    }

    public function test_is_superadmin_false_for_regular_role(): void
    {
        $role = Role::create(['name' => 'manager', 'label' => 'M']);
        UserRole::create(['user_id' => 7, 'role_id' => $role->id]);

        $this->assertFalse($this->resolver()->isSuperadmin(7));
    }

    public function test_is_superadmin_false_for_unassigned_user(): void
    {
        $this->assertFalse($this->resolver()->isSuperadmin(999));
    }
}
```

- [ ] **Step 2: Run tests, expect failures**

```bash
./vendor/bin/phpunit tests/Unit/Services/PermissionResolverTest.php
```

- [ ] **Step 3: Implement `roleOf` + `isSuperadmin`**

Add to `src/Services/PermissionResolver.php`:

```php
use Saniock\EvoAccess\Models\Role;
use Saniock\EvoAccess\Models\UserRole;

public function roleOf(int $userId): ?Role
{
    $userRole = UserRole::where('user_id', $userId)->first();
    if (!$userRole) {
        return null;
    }
    return Role::find($userRole->role_id);
}

public function isSuperadmin(int $userId): bool
{
    $role = $this->roleOf($userId);
    return $role !== null && $role->is_system === true;
}
```

- [ ] **Step 4: Run tests**

```bash
./vendor/bin/phpunit tests/Unit/Services/PermissionResolverTest.php
```

- [ ] **Step 5: Commit**

```bash
git add src/Services/PermissionResolver.php tests/Unit/Services/PermissionResolverTest.php
git commit -m "feat(resolver): roleOf + isSuperadmin helpers"
```

### Task 4.2: `loadForUser` — full SQL query logic

**Files:**
- Modify: `src/Services/PermissionResolver.php`
- Modify: `tests/Unit/Services/PermissionResolverTest.php`

- [ ] **Step 1: Add tests for loadForUser**

Append to `tests/Unit/Services/PermissionResolverTest.php`:

```php
use Saniock\EvoAccess\Models\Permission;
use Saniock\EvoAccess\Models\RolePermissionAction;

public function test_load_for_user_returns_empty_for_unassigned(): void
{
    $this->assertSame([], $this->resolver()->loadForUser(999));
}

public function test_load_for_user_returns_is_system_marker_for_superadmin(): void
{
    $superadmin = Role::where('name', 'superadmin')->firstOrFail();
    UserRole::create(['user_id' => 7, 'role_id' => $superadmin->id]);

    $map = $this->resolver()->loadForUser(7);
    $this->assertTrue($map['__is_system'] ?? false);
}

public function test_load_for_user_returns_role_grants(): void
{
    $role = Role::create(['name' => 'manager', 'label' => 'M']);
    UserRole::create(['user_id' => 7, 'role_id' => $role->id]);

    $perm = Permission::create([
        'name' => 'orders.orders', 'label' => 'L',
        'module' => 'orders', 'actions' => ['view', 'update'],
    ]);

    RolePermissionAction::create([
        'role_id' => $role->id,
        'permission_id' => $perm->id,
        'action' => 'view',
    ]);
    RolePermissionAction::create([
        'role_id' => $role->id,
        'permission_id' => $perm->id,
        'action' => 'update',
    ]);

    $map = $this->resolver()->loadForUser(7);

    $this->assertTrue($map['orders.orders']['view']);
    $this->assertTrue($map['orders.orders']['update']);
}

public function test_load_for_user_skips_orphaned_permissions(): void
{
    $role = Role::create(['name' => 'manager', 'label' => 'M']);
    UserRole::create(['user_id' => 7, 'role_id' => $role->id]);

    $perm = Permission::create([
        'name' => 'orders.deleted', 'label' => 'D',
        'module' => 'orders', 'actions' => ['view'],
        'is_orphaned' => true,
    ]);

    RolePermissionAction::create([
        'role_id' => $role->id,
        'permission_id' => $perm->id,
        'action' => 'view',
    ]);

    $map = $this->resolver()->loadForUser(7);
    $this->assertArrayNotHasKey('orders.deleted', $map);
}
```

- [ ] **Step 2: Run tests, expect failures**

```bash
./vendor/bin/phpunit tests/Unit/Services/PermissionResolverTest.php --filter load_for_user
```

- [ ] **Step 3: Implement `loadForUser`**

Add to `src/Services/PermissionResolver.php`:

```php
use Illuminate\Support\Facades\DB;

public function loadForUser(int $userId): array
{
    if (isset($this->cache[$userId])) {
        return $this->cache[$userId];
    }

    $userRole = DB::table('ea_user_roles')->where('user_id', $userId)->first();
    if (!$userRole) {
        return $this->cache[$userId] = [];
    }

    $role = DB::table('ea_roles')->where('id', $userRole->role_id)->first();
    if ($role && (int) $role->is_system === 1) {
        return $this->cache[$userId] = ['__is_system' => true];
    }

    // Load role grants
    $roleGrants = DB::table('ea_role_permission_actions as rpa')
        ->join('ea_permissions as p', 'p.id', '=', 'rpa.permission_id')
        ->where('rpa.role_id', $userRole->role_id)
        ->where('p.is_orphaned', false)
        ->select('p.name as permission', 'rpa.action')
        ->get();

    $map = [];
    foreach ($roleGrants as $row) {
        $map[$row->permission][$row->action] = true;
    }

    // Apply user overrides — grants first, then revokes (so revoke wins)
    $overrides = DB::table('ea_user_overrides as uo')
        ->join('ea_permissions as p', 'p.id', '=', 'uo.permission_id')
        ->where('uo.user_id', $userId)
        ->where('p.is_orphaned', false)
        ->select('p.name as permission', 'uo.action', 'uo.mode')
        ->get();

    foreach ($overrides as $row) {
        if ($row->mode === 'grant') {
            $map[$row->permission][$row->action] = true;
        }
    }
    foreach ($overrides as $row) {
        if ($row->mode === 'revoke') {
            $map[$row->permission][$row->action] = false;
        }
    }

    return $this->cache[$userId] = $map;
}
```

- [ ] **Step 4: Run tests**

```bash
./vendor/bin/phpunit tests/Unit/Services/PermissionResolverTest.php --filter load_for_user
```

- [ ] **Step 5: Commit**

```bash
git add src/Services/PermissionResolver.php tests/Unit/Services/PermissionResolverTest.php
git commit -m "feat(resolver): loadForUser with role grants + override merging"
```

### Task 4.3: 16-case truth table tests

**Files:**
- Create: `tests/Unit/Services/ResolverTruthTableTest.php`

This task implements the spec from `docs/design.md` §7.2 (the 16-case truth table) as a single parameterised test that exhaustively verifies the resolver's correctness.

- [ ] **Step 1: Write the truth table test**

Create `tests/Unit/Services/ResolverTruthTableTest.php`:

```php
<?php

namespace Saniock\EvoAccess\Tests\Unit\Services;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Saniock\EvoAccess\Models\Permission;
use Saniock\EvoAccess\Models\Role;
use Saniock\EvoAccess\Models\RolePermissionAction;
use Saniock\EvoAccess\Models\UserOverride;
use Saniock\EvoAccess\Models\UserRole;
use Saniock\EvoAccess\Services\PermissionResolver;
use Saniock\EvoAccess\Tests\TestCase;

class ResolverTruthTableTest extends TestCase
{
    use RefreshDatabase;

    private const PERMISSION_NAME = 'orders.orders';
    private const ACTION = 'export';
    private const USER_ID = 7;

    /**
     * 16 truth-table cases from design.md §7.2
     *
     * @return array<string, array{is_system: bool, has_revoke: bool, has_grant: bool, has_role: bool, expected: bool}>
     */
    public static function truthTableProvider(): array
    {
        return [
            'case 1:  sys=1 rev=1 grnt=1 role=1' => [true,  true,  true,  true,  true],
            'case 2:  sys=1 rev=1 grnt=1 role=0' => [true,  true,  true,  false, true],
            'case 3:  sys=1 rev=1 grnt=0 role=1' => [true,  true,  false, true,  true],
            'case 4:  sys=1 rev=1 grnt=0 role=0' => [true,  true,  false, false, true],
            'case 5:  sys=1 rev=0 grnt=1 role=1' => [true,  false, true,  true,  true],
            'case 6:  sys=1 rev=0 grnt=1 role=0' => [true,  false, true,  false, true],
            'case 7:  sys=1 rev=0 grnt=0 role=1' => [true,  false, false, true,  true],
            'case 8:  sys=1 rev=0 grnt=0 role=0' => [true,  false, false, false, true],
            'case 9:  sys=0 rev=1 grnt=1 role=1' => [false, true,  true,  true,  false],
            'case 10: sys=0 rev=1 grnt=1 role=0' => [false, true,  true,  false, false],
            'case 11: sys=0 rev=1 grnt=0 role=1' => [false, true,  false, true,  false],
            'case 12: sys=0 rev=1 grnt=0 role=0' => [false, true,  false, false, false],
            'case 13: sys=0 rev=0 grnt=1 role=1' => [false, false, true,  true,  true],
            'case 14: sys=0 rev=0 grnt=1 role=0' => [false, false, true,  false, true],
            'case 15: sys=0 rev=0 grnt=0 role=1' => [false, false, false, true,  true],
            'case 16: sys=0 rev=0 grnt=0 role=0' => [false, false, false, false, false],
        ];
    }

    /**
     * @dataProvider truthTableProvider
     */
    public function test_truth_table_case(
        bool $isSystem,
        bool $hasRevoke,
        bool $hasGrant,
        bool $hasRole,
        bool $expected,
    ): void {
        $role = $isSystem
            ? Role::where('name', 'superadmin')->firstOrFail()
            : Role::create(['name' => 'manager', 'label' => 'M']);

        UserRole::create(['user_id' => self::USER_ID, 'role_id' => $role->id]);

        $perm = Permission::create([
            'name'    => self::PERMISSION_NAME,
            'label'   => 'L',
            'module'  => 'orders',
            'actions' => ['view', self::ACTION],
        ]);

        if ($hasRole) {
            RolePermissionAction::create([
                'role_id'       => $role->id,
                'permission_id' => $perm->id,
                'action'        => self::ACTION,
            ]);
        }

        if ($hasGrant) {
            UserOverride::create([
                'user_id'       => self::USER_ID,
                'permission_id' => $perm->id,
                'action'        => self::ACTION,
                'mode'          => 'grant',
                'reason'        => 'test',
            ]);
        }

        if ($hasRevoke) {
            // Override grant if it would conflict (PK is user/perm/action without mode)
            UserOverride::where([
                'user_id'       => self::USER_ID,
                'permission_id' => $perm->id,
                'action'        => self::ACTION,
            ])->delete();

            UserOverride::create([
                'user_id'       => self::USER_ID,
                'permission_id' => $perm->id,
                'action'        => self::ACTION,
                'mode'          => 'revoke',
                'reason'        => 'test',
            ]);
        }

        $resolver = $this->app->make(PermissionResolver::class);
        $result = $resolver->userHas(self::USER_ID, self::PERMISSION_NAME, self::ACTION);

        $this->assertSame($expected, $result);
    }
}
```

> **Note for the implementer:** The test schema constraint `PRIMARY KEY (user_id, permission_id, action)` on `ea_user_overrides` means a user cannot have both a grant and a revoke for the same `(perm, action)` simultaneously. The test handles this by deleting the grant before inserting the revoke. The truth table cases that have both `hasRevoke=true` AND `hasGrant=true` are testing conceptual situations — in practice the resolver only sees one or the other after the schema constraint.
>
> For cases 1, 2, 5, 6, 9, 10, 13, 14 where both grant and revoke are theoretically present, the test simulates "what the resolver would do given both inputs". Since `is_system=1` short-circuits everything, cases 1-8 pass regardless. For cases 9, 10, 13, 14 the test asserts that revoke wins when present.

- [ ] **Step 2: Add `userHas` to resolver if not already present**

In `src/Services/PermissionResolver.php`, add:

```php
public function userHas(int $userId, string $permission, string $action): bool
{
    $map = $this->loadForUser($userId);

    if (isset($map['__is_system']) && $map['__is_system'] === true) {
        return true;
    }

    return $map[$permission][$action] ?? false;
}
```

- [ ] **Step 3: Run truth table tests**

```bash
./vendor/bin/phpunit tests/Unit/Services/ResolverTruthTableTest.php
```

Expected: 16 tests, 16 assertions, all passing.

- [ ] **Step 4: Commit**

```bash
git add src/Services/PermissionResolver.php tests/Unit/Services/ResolverTruthTableTest.php
git commit -m "test(resolver): exhaustive 16-case truth table from design.md §7.2"
```

### Task 4.4: Cache invalidation methods

**Files:**
- Modify: `src/Services/PermissionResolver.php`
- Modify: `tests/Unit/Services/PermissionResolverTest.php`

- [ ] **Step 1: Add cache tests**

Append to `tests/Unit/Services/PermissionResolverTest.php`:

```php
public function test_forget_user_clears_one_users_cache(): void
{
    $resolver = $this->resolver();

    $role = Role::create(['name' => 'manager', 'label' => 'M']);
    UserRole::create(['user_id' => 7, 'role_id' => $role->id]);

    $resolver->loadForUser(7);
    $resolver->loadForUser(8);

    $resolver->forgetUser(7);

    // Re-loading user 7 should hit DB again (verified by adding a grant after)
    $perm = Permission::create([
        'name' => 'orders.orders', 'label' => 'L',
        'module' => 'orders', 'actions' => ['view'],
    ]);
    RolePermissionAction::create([
        'role_id' => $role->id,
        'permission_id' => $perm->id,
        'action' => 'view',
    ]);

    $map = $resolver->loadForUser(7);
    $this->assertTrue($map['orders.orders']['view'] ?? false);
}

public function test_forget_all_clears_all_caches(): void
{
    $resolver = $this->resolver();

    $role = Role::create(['name' => 'manager', 'label' => 'M']);
    UserRole::create(['user_id' => 7, 'role_id' => $role->id]);
    UserRole::create(['user_id' => 8, 'role_id' => $role->id]);

    $resolver->loadForUser(7);
    $resolver->loadForUser(8);

    $resolver->forgetAll();

    $perm = Permission::create([
        'name' => 'orders.orders', 'label' => 'L',
        'module' => 'orders', 'actions' => ['view'],
    ]);
    RolePermissionAction::create([
        'role_id' => $role->id,
        'permission_id' => $perm->id,
        'action' => 'view',
    ]);

    $this->assertTrue($resolver->loadForUser(7)['orders.orders']['view'] ?? false);
    $this->assertTrue($resolver->loadForUser(8)['orders.orders']['view'] ?? false);
}
```

- [ ] **Step 2: Implement cache methods**

Verify these are in `src/Services/PermissionResolver.php` (they exist as stubs from scaffold, ensure they work):

```php
public function forgetUser(int $userId): void
{
    unset($this->cache[$userId]);
}

public function forgetAll(): void
{
    $this->cache = [];
}
```

- [ ] **Step 3: Run tests**

```bash
./vendor/bin/phpunit tests/Unit/Services/PermissionResolverTest.php --filter forget
```

- [ ] **Step 4: Commit**

```bash
git add src/Services/PermissionResolver.php tests/Unit/Services/PermissionResolverTest.php
git commit -m "test(resolver): verify cache invalidation via forgetUser/forgetAll"
```

### Task 4.5: `effectiveActions` helper

**Files:**
- Modify: `src/Services/PermissionResolver.php`
- Modify: `tests/Unit/Services/PermissionResolverTest.php`

- [ ] **Step 1: Add test**

Append:

```php
public function test_effective_actions_returns_granted_actions(): void
{
    $role = Role::create(['name' => 'manager', 'label' => 'M']);
    UserRole::create(['user_id' => 7, 'role_id' => $role->id]);

    $perm = Permission::create([
        'name' => 'orders.orders', 'label' => 'L',
        'module' => 'orders', 'actions' => ['view', 'update', 'export'],
    ]);

    RolePermissionAction::create(['role_id' => $role->id, 'permission_id' => $perm->id, 'action' => 'view']);
    RolePermissionAction::create(['role_id' => $role->id, 'permission_id' => $perm->id, 'action' => 'update']);

    $actions = $this->resolver()->effectiveActions(7, 'orders.orders');
    sort($actions);
    $this->assertSame(['update', 'view'], $actions);
}
```

- [ ] **Step 2: Implement**

Add to `src/Services/PermissionResolver.php`:

```php
public function effectiveActions(int $userId, string $permission): array
{
    $map = $this->loadForUser($userId);
    $perPermission = $map[$permission] ?? [];

    return array_keys(array_filter($perPermission, fn($v) => $v === true));
}
```

- [ ] **Step 3: Run test**

```bash
./vendor/bin/phpunit tests/Unit/Services/PermissionResolverTest.php --filter effective
```

- [ ] **Step 4: Commit**

```bash
git add src/Services/PermissionResolver.php tests/Unit/Services/PermissionResolverTest.php
git commit -m "feat(resolver): effectiveActions helper"
```

---

## Phase 5: AccessService implementation

The public façade. Each method is short and delegates to PermissionCatalog or PermissionResolver. Most of the complexity lives in `canView`/`canEdit` (menu resolution) and `filterMenu` (recursive tree filter).

### Task 5.1: `can` + `authorize`

**Files:**
- Modify: `src/Services/AccessService.php`
- Create: `tests/Unit/Services/AccessServiceTest.php`

- [ ] **Step 1: Write tests**

```php
<?php

namespace Saniock\EvoAccess\Tests\Unit\Services;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Saniock\EvoAccess\Exceptions\AccessDeniedException;
use Saniock\EvoAccess\Models\Permission;
use Saniock\EvoAccess\Models\Role;
use Saniock\EvoAccess\Models\RolePermissionAction;
use Saniock\EvoAccess\Models\UserRole;
use Saniock\EvoAccess\Services\AccessService;
use Saniock\EvoAccess\Tests\TestCase;

class AccessServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): AccessService
    {
        return $this->app->make(AccessService::class);
    }

    private function setupUserWithGrant(string $action = 'view'): void
    {
        $role = Role::create(['name' => 'manager', 'label' => 'M']);
        UserRole::create(['user_id' => 7, 'role_id' => $role->id]);

        $perm = Permission::create([
            'name' => 'orders.orders', 'label' => 'L',
            'module' => 'orders', 'actions' => ['view', 'update'],
        ]);

        RolePermissionAction::create([
            'role_id' => $role->id,
            'permission_id' => $perm->id,
            'action' => $action,
        ]);
    }

    public function test_can_returns_true_for_granted(): void
    {
        $this->setupUserWithGrant('view');
        $this->assertTrue($this->service()->can('orders.orders', 'view', 7));
    }

    public function test_can_returns_false_for_not_granted(): void
    {
        $this->setupUserWithGrant('view');
        $this->assertFalse($this->service()->can('orders.orders', 'export', 7));
    }

    public function test_authorize_throws_when_denied(): void
    {
        $this->setupUserWithGrant('view');

        $this->expectException(AccessDeniedException::class);
        $this->service()->authorize('orders.orders', 'export', 7);
    }

    public function test_authorize_silent_when_allowed(): void
    {
        $this->setupUserWithGrant('view');
        $this->service()->authorize('orders.orders', 'view', 7);
        $this->assertTrue(true);  // no exception thrown
    }
}
```

- [ ] **Step 2: Run tests, expect failures**

```bash
./vendor/bin/phpunit tests/Unit/Services/AccessServiceTest.php
```

- [ ] **Step 3: Implement `can` + `authorize`**

In `src/Services/AccessService.php`:

```php
use Saniock\EvoAccess\Exceptions\AccessDeniedException;

public function can(string $permission, string $action, int $userId): bool
{
    return $this->resolver->userHas($userId, $permission, $action);
}

public function authorize(string $permission, string $action, int $userId): void
{
    if (!$this->can($permission, $action, $userId)) {
        throw new AccessDeniedException(
            "Access denied: user $userId cannot $action on $permission",
            permission: $permission,
            action: $action,
            userId: $userId,
        );
    }
}
```

- [ ] **Step 4: Run tests**

```bash
./vendor/bin/phpunit tests/Unit/Services/AccessServiceTest.php
```

- [ ] **Step 5: Commit**

```bash
git add src/Services/AccessService.php tests/Unit/Services/AccessServiceTest.php
git commit -m "feat(access): implement can + authorize"
```

### Task 5.2: `canView` + `canEdit` (menu resolution)

**Files:**
- Modify: `src/Services/AccessService.php`
- Modify: `tests/Unit/Services/AccessServiceTest.php`

- [ ] **Step 1: Add tests**

Append to `tests/Unit/Services/AccessServiceTest.php`:

```php
private function ordersMenu(): array
{
    return [
        [
            'id' => 'orders',
            'title' => 'Orders',
            'items' => [
                ['id' => 'orders',   'title' => 'List',     'permission' => 'orders.orders'],
                ['id' => 'payments', 'title' => 'Payments', 'permission' => 'orders.payments'],
            ],
        ],
    ];
}

public function test_can_view_resolves_menu_item_to_permission(): void
{
    $this->setupUserWithGrant('view');
    $this->assertTrue($this->service()->canView($this->ordersMenu(), 'orders', 7));
}

public function test_can_view_false_when_action_id_unknown_in_menu_returns_true(): void
{
    // Action IDs not present in menu (like AJAX endpoints) are not blocked
    $this->setupUserWithGrant('view');
    $this->assertTrue($this->service()->canView($this->ordersMenu(), 'unknown_ajax', 7));
}

public function test_can_edit_uses_update_action(): void
{
    $this->setupUserWithGrant('update');
    $this->assertTrue($this->service()->canEdit($this->ordersMenu(), 'orders', 7));
}
```

- [ ] **Step 2: Run tests, expect failures**

```bash
./vendor/bin/phpunit tests/Unit/Services/AccessServiceTest.php --filter canView
```

- [ ] **Step 3: Implement `canView` + `canEdit` + private menu resolver**

In `src/Services/AccessService.php`:

```php
public function canView(array $menu, string $actionId, int $userId): bool
{
    $permission = $this->resolveMenuPermission($menu, $actionId);
    if ($permission === null) {
        return true;  // Action ID not described in menu — allow (e.g. AJAX endpoints)
    }
    return $this->can($permission, 'view', $userId);
}

public function canEdit(array $menu, string $actionId, int $userId): bool
{
    $permission = $this->resolveMenuPermission($menu, $actionId);
    if ($permission === null) {
        return true;
    }
    return $this->can($permission, 'update', $userId);
}

private function resolveMenuPermission(array $menu, string $actionId): ?string
{
    foreach ($menu as $item) {
        if (($item['id'] ?? null) === $actionId) {
            return $item['permission'] ?? null;
        }
        if (!empty($item['items'])) {
            $nested = $this->resolveMenuPermission($item['items'], $actionId);
            if ($nested !== null) {
                return $nested;
            }
        }
    }
    return null;
}
```

- [ ] **Step 4: Run tests**

```bash
./vendor/bin/phpunit tests/Unit/Services/AccessServiceTest.php
```

- [ ] **Step 5: Commit**

```bash
git add src/Services/AccessService.php tests/Unit/Services/AccessServiceTest.php
git commit -m "feat(access): canView/canEdit with menu resolution"
```

### Task 5.3: `filterMenu` (recursive tree filter)

**Files:**
- Modify: `src/Services/AccessService.php`
- Modify: `tests/Unit/Services/AccessServiceTest.php`

- [ ] **Step 1: Add tests**

Append:

```php
public function test_filter_menu_keeps_visible_items(): void
{
    $this->setupUserWithGrant('view');

    $filtered = $this->service()->filterMenu($this->ordersMenu(), 7);
    $this->assertCount(1, $filtered);
    $this->assertCount(1, $filtered[0]['items']);
    $this->assertSame('orders', $filtered[0]['items'][0]['id']);
}

public function test_filter_menu_drops_items_without_view(): void
{
    // user has no role at all
    $filtered = $this->service()->filterMenu($this->ordersMenu(), 999);
    $this->assertEmpty($filtered);
}

public function test_filter_menu_collapses_empty_groups(): void
{
    $this->setupUserWithGrant('view');

    $menu = [
        [
            'id' => 'finances',
            'title' => 'Finances',
            'items' => [
                ['id' => 'banks', 'title' => 'Banks', 'permission' => 'finances.banks'],
            ],
        ],
        [
            'id' => 'orders',
            'title' => 'Orders',
            'items' => [
                ['id' => 'orders', 'title' => 'List', 'permission' => 'orders.orders'],
            ],
        ],
    ];

    // User has 'orders.orders' grant but no 'finances.banks' — finances group should be collapsed
    $filtered = $this->service()->filterMenu($menu, 7);
    $this->assertCount(1, $filtered);
    $this->assertSame('orders', $filtered[0]['id']);
}
```

- [ ] **Step 2: Run tests, expect failures**

```bash
./vendor/bin/phpunit tests/Unit/Services/AccessServiceTest.php --filter filter_menu
```

- [ ] **Step 3: Implement `filterMenu`**

In `src/Services/AccessService.php`:

```php
public function filterMenu(array $menu, int $userId): array
{
    $out = [];

    foreach ($menu as $item) {
        // Has children? Recurse first.
        if (!empty($item['items'])) {
            $children = $this->filterMenu($item['items'], $userId);
            if (empty($children)) {
                continue;  // group is empty after filtering — drop it
            }
            $copy = $item;
            $copy['items'] = $children;
            $out[] = $copy;
            continue;
        }

        // Leaf item — check view permission
        $permission = $item['permission'] ?? null;
        if ($permission === null) {
            $out[] = $item;  // No permission tag → always visible
            continue;
        }

        if ($this->can($permission, 'view', $userId)) {
            $out[] = $item;
        }
    }

    return $out;
}
```

- [ ] **Step 4: Run tests**

```bash
./vendor/bin/phpunit tests/Unit/Services/AccessServiceTest.php --filter filter_menu
```

- [ ] **Step 5: Commit**

```bash
git add src/Services/AccessService.php tests/Unit/Services/AccessServiceTest.php
git commit -m "feat(access): filterMenu with recursive tree filtering"
```

### Task 5.4: `actionsFor` + `registerPermissions` delegation

**Files:**
- Modify: `src/Services/AccessService.php`
- Modify: `tests/Unit/Services/AccessServiceTest.php`

- [ ] **Step 1: Add tests**

```php
public function test_actions_for_returns_action_map(): void
{
    $role = Role::create(['name' => 'manager', 'label' => 'M']);
    UserRole::create(['user_id' => 7, 'role_id' => $role->id]);

    $perm = Permission::create([
        'name' => 'orders.orders', 'label' => 'L',
        'module' => 'orders', 'actions' => ['view', 'update', 'export'],
    ]);

    RolePermissionAction::create(['role_id' => $role->id, 'permission_id' => $perm->id, 'action' => 'view']);
    RolePermissionAction::create(['role_id' => $role->id, 'permission_id' => $perm->id, 'action' => 'update']);

    $actions = $this->service()->actionsFor('orders.orders', 7);

    $this->assertTrue($actions['view']);
    $this->assertTrue($actions['update']);
    $this->assertFalse($actions['export']);
}

public function test_register_permissions_delegates_to_catalog(): void
{
    $this->service()->registerPermissions('orders', [
        ['name' => 'orders.orders', 'label' => 'L', 'actions' => ['view']],
    ]);

    $catalog = $this->app->make(\Saniock\EvoAccess\Services\PermissionCatalog::class);
    $this->assertNotNull($catalog->find('orders.orders'));
}
```

- [ ] **Step 2: Implement**

In `src/Services/AccessService.php`:

```php
public function actionsFor(string $permission, int $userId): array
{
    $catalogEntry = $this->catalog->find($permission);

    // Determine which actions to report. Prefer the catalog's declared actions
    // for the permission, falling back to a hardcoded standard list.
    $allActions = $catalogEntry['actions'] ?? ['view', 'create', 'update', 'delete', 'export'];

    $effective = $this->resolver->effectiveActions($userId, $permission);

    $result = [];
    foreach ($allActions as $action) {
        $result[$action] = in_array($action, $effective, true);
    }

    return $result;
}

public function registerPermissions(string $module, array $permissions): void
{
    $this->catalog->registerPermissions($module, $permissions);
}
```

- [ ] **Step 3: Run tests**

```bash
./vendor/bin/phpunit tests/Unit/Services/AccessServiceTest.php
```

- [ ] **Step 4: Commit**

```bash
git add src/Services/AccessService.php tests/Unit/Services/AccessServiceTest.php
git commit -m "feat(access): actionsFor + registerPermissions delegation"
```

---

## Phase 6: AuditLogger implementation

### Task 6.1: Universal `log` method + read API

**Files:**
- Modify: `src/Services/AuditLogger.php`
- Create: `tests/Unit/Services/AuditLoggerTest.php`

- [ ] **Step 1: Write tests**

```php
<?php

namespace Saniock\EvoAccess\Tests\Unit\Services;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Saniock\EvoAccess\Models\AuditLog;
use Saniock\EvoAccess\Services\AuditLogger;
use Saniock\EvoAccess\Tests\TestCase;

class AuditLoggerTest extends TestCase
{
    use RefreshDatabase;

    private function logger(): AuditLogger
    {
        return $this->app->make(AuditLogger::class);
    }

    public function test_log_writes_a_row(): void
    {
        $this->logger()->log(
            actorUserId: 7,
            action: 'create_role',
            targetRoleId: 1,
            details: ['name' => 'manager'],
        );

        $this->assertSame(1, AuditLog::count());

        $row = AuditLog::first();
        $this->assertSame(7, $row->actor_user_id);
        $this->assertSame('create_role', $row->action);
        $this->assertSame(['name' => 'manager'], $row->details);
    }

    public function test_recent_returns_latest_entries(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->logger()->log(actorUserId: 7, action: 'test_' . $i);
        }

        $recent = $this->logger()->recent(3);
        $this->assertCount(3, $recent);
    }

    public function test_entries_for_user_filters_by_target(): void
    {
        $this->logger()->log(actorUserId: 7, action: 'assign', targetUserId: 42);
        $this->logger()->log(actorUserId: 7, action: 'assign', targetUserId: 99);

        $entries = $this->logger()->entriesForUser(42);
        $this->assertCount(1, $entries);
    }
}
```

- [ ] **Step 2: Run tests, expect failures**

```bash
./vendor/bin/phpunit tests/Unit/Services/AuditLoggerTest.php
```

- [ ] **Step 3: Implement universal `log` + read API**

Replace `src/Services/AuditLogger.php`:

```php
<?php

namespace Saniock\EvoAccess\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Saniock\EvoAccess\Models\AuditLog;
use Saniock\EvoAccess\Models\Role;

class AuditLogger
{
    public function log(
        int $actorUserId,
        string $action,
        ?int $targetRoleId = null,
        ?int $targetUserId = null,
        ?int $permissionId = null,
        ?string $oldValue = null,
        ?string $newValue = null,
        array $details = [],
    ): void {
        AuditLog::create([
            'actor_user_id'  => $actorUserId,
            'action'         => $action,
            'target_role_id' => $targetRoleId,
            'target_user_id' => $targetUserId,
            'permission_id'  => $permissionId,
            'old_value'      => $oldValue,
            'new_value'      => $newValue,
            'details'        => $details ?: null,
        ]);
    }

    // ─── Type-safe wrappers ──────────────────────────────────────────────

    public function logRoleCreated(int $actorId, Role $role): void
    {
        $this->log(
            actorUserId: $actorId,
            action: 'role_created',
            targetRoleId: $role->id,
            newValue: $role->name,
            details: ['label' => $role->label],
        );
    }

    public function logRoleRenamed(int $actorId, Role $role, string $oldLabel, string $newLabel): void
    {
        $this->log(
            actorUserId: $actorId,
            action: 'role_renamed',
            targetRoleId: $role->id,
            oldValue: $oldLabel,
            newValue: $newLabel,
        );
    }

    public function logRoleDeleted(int $actorId, Role $role): void
    {
        $this->log(
            actorUserId: $actorId,
            action: 'role_deleted',
            targetRoleId: $role->id,
            oldValue: $role->name,
        );
    }

    public function logRoleCloned(int $actorId, Role $sourceRole, Role $newRole): void
    {
        $this->log(
            actorUserId: $actorId,
            action: 'role_cloned',
            targetRoleId: $newRole->id,
            details: ['source_role_id' => $sourceRole->id, 'source_name' => $sourceRole->name],
        );
    }

    public function logGrant(int $actorId, int $roleId, int $permissionId, string $action): void
    {
        $this->log(
            actorUserId: $actorId,
            action: 'grant',
            targetRoleId: $roleId,
            permissionId: $permissionId,
            newValue: $action,
        );
    }

    public function logRevoke(int $actorId, int $roleId, int $permissionId, string $action): void
    {
        $this->log(
            actorUserId: $actorId,
            action: 'revoke',
            targetRoleId: $roleId,
            permissionId: $permissionId,
            oldValue: $action,
        );
    }

    public function logUserAssigned(int $actorId, int $userId, int $roleId): void
    {
        $this->log(
            actorUserId: $actorId,
            action: 'user_assigned',
            targetRoleId: $roleId,
            targetUserId: $userId,
        );
    }

    public function logUserUnassigned(int $actorId, int $userId, int $oldRoleId): void
    {
        $this->log(
            actorUserId: $actorId,
            action: 'user_unassigned',
            targetRoleId: $oldRoleId,
            targetUserId: $userId,
        );
    }

    public function logUserRoleChanged(int $actorId, int $userId, int $oldRoleId, int $newRoleId): void
    {
        $this->log(
            actorUserId: $actorId,
            action: 'user_role_changed',
            targetRoleId: $newRoleId,
            targetUserId: $userId,
            details: ['old_role_id' => $oldRoleId, 'new_role_id' => $newRoleId],
        );
    }

    public function logOverrideAdded(int $actorId, int $userId, int $permissionId, string $action, string $mode, ?string $reason): void
    {
        $this->log(
            actorUserId: $actorId,
            action: "override_$mode",
            targetUserId: $userId,
            permissionId: $permissionId,
            newValue: $action,
            details: ['reason' => $reason],
        );
    }

    public function logOverrideRemoved(int $actorId, int $userId, int $permissionId, string $action): void
    {
        $this->log(
            actorUserId: $actorId,
            action: 'override_removed',
            targetUserId: $userId,
            permissionId: $permissionId,
            oldValue: $action,
        );
    }

    public function logPermissionsSync(int $actorId, int $created, int $updated, int $orphaned): void
    {
        $this->log(
            actorUserId: $actorId,
            action: 'permissions_sync',
            details: ['created' => $created, 'updated' => $updated, 'orphaned' => $orphaned],
        );
    }

    // ─── Read API ────────────────────────────────────────────────────────

    public function entriesForUser(int $userId, int $limit = 100, int $offset = 0): Collection
    {
        return AuditLog::where('target_user_id', $userId)
            ->orderByDesc('created_at')
            ->offset($offset)
            ->limit($limit)
            ->get();
    }

    public function entriesForRole(int $roleId, int $limit = 100, int $offset = 0): Collection
    {
        return AuditLog::where('target_role_id', $roleId)
            ->orderByDesc('created_at')
            ->offset($offset)
            ->limit($limit)
            ->get();
    }

    public function entriesByActor(int $actorId, int $limit = 100, int $offset = 0): Collection
    {
        return AuditLog::where('actor_user_id', $actorId)
            ->orderByDesc('created_at')
            ->offset($offset)
            ->limit($limit)
            ->get();
    }

    public function search(array $filters, int $limit = 100, int $offset = 0): Collection
    {
        $query = AuditLog::query();

        if (!empty($filters['actor_user_id'])) {
            $query->where('actor_user_id', $filters['actor_user_id']);
        }
        if (!empty($filters['target_user_id'])) {
            $query->where('target_user_id', $filters['target_user_id']);
        }
        if (!empty($filters['target_role_id'])) {
            $query->where('target_role_id', $filters['target_role_id']);
        }
        if (!empty($filters['action'])) {
            $query->where('action', $filters['action']);
        }
        if (!empty($filters['from'])) {
            $query->where('created_at', '>=', $filters['from']);
        }
        if (!empty($filters['to'])) {
            $query->where('created_at', '<=', $filters['to']);
        }

        return $query->orderByDesc('created_at')
            ->offset($offset)
            ->limit($limit)
            ->get();
    }

    public function recent(int $limit = 100): Collection
    {
        return AuditLog::orderByDesc('created_at')->limit($limit)->get();
    }
}
```

- [ ] **Step 4: Run tests**

```bash
./vendor/bin/phpunit tests/Unit/Services/AuditLoggerTest.php
```

Expected: All 3 tests pass.

- [ ] **Step 5: Commit**

```bash
git add src/Services/AuditLogger.php tests/Unit/Services/AuditLoggerTest.php
git commit -m "feat(audit): full AuditLogger with universal log + type-safe wrappers + read API"
```

---

## Phase 7: Observers

Each observer is a thin class that listens to one model and triggers AuditLogger writes + cache invalidation. All four follow the same pattern.

### Task 7.1: Create all four observers

**Files:**
- Create: `src/Observers/RoleObserver.php`
- Create: `src/Observers/RolePermissionActionObserver.php`
- Create: `src/Observers/UserRoleObserver.php`
- Create: `src/Observers/UserOverrideObserver.php`
- Create: `tests/Feature/ObserverTest.php`

- [ ] **Step 1: Create `RoleObserver`**

Create `src/Observers/RoleObserver.php`:

```php
<?php

namespace Saniock\EvoAccess\Observers;

use Saniock\EvoAccess\Models\Role;
use Saniock\EvoAccess\Services\AuditLogger;
use Saniock\EvoAccess\Services\PermissionResolver;

class RoleObserver
{
    public function __construct(
        private readonly PermissionResolver $resolver,
        private readonly AuditLogger $audit,
    ) {}

    public function created(Role $role): void
    {
        $this->audit->logRoleCreated($this->actorId(), $role);
    }

    public function updated(Role $role): void
    {
        if ($role->wasChanged('label')) {
            $this->audit->logRoleRenamed(
                $this->actorId(),
                $role,
                $role->getOriginal('label'),
                $role->label,
            );
        }

        if ($role->wasChanged('is_system')) {
            $this->resolver->forgetAll();
        }
    }

    public function deleted(Role $role): void
    {
        $this->audit->logRoleDeleted($this->actorId(), $role);
        $this->resolver->forgetAll();
    }

    private function actorId(): int
    {
        if (function_exists('evo')) {
            return (int) evo()->getLoginUserID('mgr');
        }
        return 0;  // CLI / test environment
    }
}
```

- [ ] **Step 2: Create `RolePermissionActionObserver`**

Create `src/Observers/RolePermissionActionObserver.php`:

```php
<?php

namespace Saniock\EvoAccess\Observers;

use Saniock\EvoAccess\Models\RolePermissionAction;
use Saniock\EvoAccess\Services\AuditLogger;
use Saniock\EvoAccess\Services\PermissionResolver;

class RolePermissionActionObserver
{
    public function __construct(
        private readonly PermissionResolver $resolver,
        private readonly AuditLogger $audit,
    ) {}

    public function created(RolePermissionAction $grant): void
    {
        $this->audit->logGrant(
            $this->actorId(),
            $grant->role_id,
            $grant->permission_id,
            $grant->action,
        );
        $this->resolver->forgetAll();
    }

    public function deleted(RolePermissionAction $grant): void
    {
        $this->audit->logRevoke(
            $this->actorId(),
            $grant->role_id,
            $grant->permission_id,
            $grant->action,
        );
        $this->resolver->forgetAll();
    }

    private function actorId(): int
    {
        if (function_exists('evo')) {
            return (int) evo()->getLoginUserID('mgr');
        }
        return 0;
    }
}
```

- [ ] **Step 3: Create `UserRoleObserver`**

Create `src/Observers/UserRoleObserver.php`:

```php
<?php

namespace Saniock\EvoAccess\Observers;

use Saniock\EvoAccess\Models\UserRole;
use Saniock\EvoAccess\Services\AuditLogger;
use Saniock\EvoAccess\Services\PermissionResolver;

class UserRoleObserver
{
    public function __construct(
        private readonly PermissionResolver $resolver,
        private readonly AuditLogger $audit,
    ) {}

    public function created(UserRole $assignment): void
    {
        $this->audit->logUserAssigned(
            $this->actorId(),
            $assignment->user_id,
            $assignment->role_id,
        );
        $this->resolver->forgetUser($assignment->user_id);
    }

    public function updated(UserRole $assignment): void
    {
        if ($assignment->wasChanged('role_id')) {
            $this->audit->logUserRoleChanged(
                $this->actorId(),
                $assignment->user_id,
                (int) $assignment->getOriginal('role_id'),
                $assignment->role_id,
            );
            $this->resolver->forgetUser($assignment->user_id);
        }
    }

    public function deleted(UserRole $assignment): void
    {
        $this->audit->logUserUnassigned(
            $this->actorId(),
            $assignment->user_id,
            $assignment->role_id,
        );
        $this->resolver->forgetUser($assignment->user_id);
    }

    private function actorId(): int
    {
        if (function_exists('evo')) {
            return (int) evo()->getLoginUserID('mgr');
        }
        return 0;
    }
}
```

- [ ] **Step 4: Create `UserOverrideObserver`**

Create `src/Observers/UserOverrideObserver.php`:

```php
<?php

namespace Saniock\EvoAccess\Observers;

use Saniock\EvoAccess\Models\UserOverride;
use Saniock\EvoAccess\Services\AuditLogger;
use Saniock\EvoAccess\Services\PermissionResolver;

class UserOverrideObserver
{
    public function __construct(
        private readonly PermissionResolver $resolver,
        private readonly AuditLogger $audit,
    ) {}

    public function created(UserOverride $override): void
    {
        $this->audit->logOverrideAdded(
            $this->actorId(),
            $override->user_id,
            $override->permission_id,
            $override->action,
            $override->mode,
            $override->reason,
        );
        $this->resolver->forgetUser($override->user_id);
    }

    public function deleted(UserOverride $override): void
    {
        $this->audit->logOverrideRemoved(
            $this->actorId(),
            $override->user_id,
            $override->permission_id,
            $override->action,
        );
        $this->resolver->forgetUser($override->user_id);
    }

    private function actorId(): int
    {
        if (function_exists('evo')) {
            return (int) evo()->getLoginUserID('mgr');
        }
        return 0;
    }
}
```

- [ ] **Step 5: Wire observers in service provider**

Edit `src/EvoAccessServiceProvider.php` `boot()` method, add at the end:

```php
\Saniock\EvoAccess\Models\Role::observe(\Saniock\EvoAccess\Observers\RoleObserver::class);
\Saniock\EvoAccess\Models\RolePermissionAction::observe(\Saniock\EvoAccess\Observers\RolePermissionActionObserver::class);
\Saniock\EvoAccess\Models\UserRole::observe(\Saniock\EvoAccess\Observers\UserRoleObserver::class);
\Saniock\EvoAccess\Models\UserOverride::observe(\Saniock\EvoAccess\Observers\UserOverrideObserver::class);
```

- [ ] **Step 6: Write integration test**

Create `tests/Feature/ObserverTest.php`:

```php
<?php

namespace Saniock\EvoAccess\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Saniock\EvoAccess\Models\AuditLog;
use Saniock\EvoAccess\Models\Permission;
use Saniock\EvoAccess\Models\Role;
use Saniock\EvoAccess\Models\RolePermissionAction;
use Saniock\EvoAccess\Models\UserOverride;
use Saniock\EvoAccess\Models\UserRole;
use Saniock\EvoAccess\Tests\TestCase;

class ObserverTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_creation_writes_audit(): void
    {
        Role::create(['name' => 'manager', 'label' => 'M']);

        $entry = AuditLog::where('action', 'role_created')->first();
        $this->assertNotNull($entry);
    }

    public function test_grant_creation_writes_audit(): void
    {
        $role = Role::create(['name' => 'manager', 'label' => 'M']);
        $perm = Permission::create([
            'name' => 'orders.orders', 'label' => 'L',
            'module' => 'orders', 'actions' => ['view'],
        ]);

        RolePermissionAction::create([
            'role_id' => $role->id,
            'permission_id' => $perm->id,
            'action' => 'view',
        ]);

        $entry = AuditLog::where('action', 'grant')->first();
        $this->assertNotNull($entry);
        $this->assertSame('view', $entry->new_value);
    }

    public function test_override_creation_writes_audit(): void
    {
        $perm = Permission::create([
            'name' => 'orders.orders', 'label' => 'L',
            'module' => 'orders', 'actions' => ['view', 'export'],
        ]);

        UserOverride::create([
            'user_id' => 7,
            'permission_id' => $perm->id,
            'action' => 'export',
            'mode' => 'grant',
            'reason' => 'test',
        ]);

        $entry = AuditLog::where('action', 'override_grant')->first();
        $this->assertNotNull($entry);
    }
}
```

- [ ] **Step 7: Run tests**

```bash
./vendor/bin/phpunit tests/Feature/ObserverTest.php
```

- [ ] **Step 8: Commit**

```bash
git add src/Observers/ src/EvoAccessServiceProvider.php tests/Feature/ObserverTest.php
git commit -m "feat(observers): wire 4 model observers for audit + cache invalidation"
```

---

## Phase 8: Console commands

### Task 8.1: `BootstrapCommand`

**Files:**
- Create: `src/Console/BootstrapCommand.php`
- Create: `tests/Feature/BootstrapCommandTest.php`

- [ ] **Step 1: Write test**

```php
<?php

namespace Saniock\EvoAccess\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Saniock\EvoAccess\Models\Role;
use Saniock\EvoAccess\Models\UserRole;
use Saniock\EvoAccess\Tests\TestCase;

class BootstrapCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_bootstrap_assigns_configured_users_to_superadmin(): void
    {
        config(['evoAccess.bootstrap_superadmin_user_ids' => [7, 42]]);

        $this->artisan('evoaccess:bootstrap')
            ->expectsOutputToContain('Bootstrap complete')
            ->assertSuccessful();

        $superadmin = Role::where('name', 'superadmin')->firstOrFail();
        $this->assertSame($superadmin->id, UserRole::where('user_id', 7)->value('role_id'));
        $this->assertSame($superadmin->id, UserRole::where('user_id', 42)->value('role_id'));
    }

    public function test_bootstrap_is_idempotent(): void
    {
        config(['evoAccess.bootstrap_superadmin_user_ids' => [7]]);

        $this->artisan('evoaccess:bootstrap')->assertSuccessful();
        $this->artisan('evoaccess:bootstrap')->assertSuccessful();

        $this->assertSame(1, UserRole::where('user_id', 7)->count());
    }

    public function test_bootstrap_warns_on_existing_non_superadmin(): void
    {
        $manager = Role::create(['name' => 'manager', 'label' => 'M']);
        UserRole::create(['user_id' => 7, 'role_id' => $manager->id]);

        config(['evoAccess.bootstrap_superadmin_user_ids' => [7]]);

        $this->artisan('evoaccess:bootstrap')
            ->expectsOutputToContain('NOT superadmin')
            ->assertSuccessful();

        $this->assertSame($manager->id, UserRole::where('user_id', 7)->value('role_id'));
    }
}
```

- [ ] **Step 2: Run, expect failures**

```bash
./vendor/bin/phpunit tests/Feature/BootstrapCommandTest.php
```

- [ ] **Step 3: Create the command**

Create `src/Console/BootstrapCommand.php`:

```php
<?php

namespace Saniock\EvoAccess\Console;

use Illuminate\Console\Command;
use Saniock\EvoAccess\Models\Role;
use Saniock\EvoAccess\Models\UserRole;

class BootstrapCommand extends Command
{
    protected $signature = 'evoaccess:bootstrap {--dry-run}';
    protected $description = 'Ensure bootstrap superadmin user IDs from config are assigned to the system role.';

    public function handle(): int
    {
        $userIds = (array) config('evoAccess.bootstrap_superadmin_user_ids', []);

        if (empty($userIds)) {
            $this->warn('No bootstrap user IDs configured. Edit config/evoAccess.php to add some.');
            return self::SUCCESS;
        }

        $superadmin = Role::where('name', 'superadmin')->where('is_system', 1)->first();
        if (!$superadmin) {
            $this->error('Superadmin role not found. Did the migrations run?');
            return self::FAILURE;
        }

        $created = 0;
        $existing = 0;

        foreach ($userIds as $userId) {
            $userId = (int) $userId;
            $existingRow = UserRole::where('user_id', $userId)->first();

            if ($existingRow) {
                if ($existingRow->role_id !== $superadmin->id) {
                    $this->warn("User {$userId} has role {$existingRow->role_id}, NOT superadmin. Skipping (use admin UI to change).");
                }
                $existing++;
                continue;
            }

            if (!$this->option('dry-run')) {
                UserRole::create([
                    'user_id'     => $userId,
                    'role_id'     => $superadmin->id,
                    'assigned_by' => null,
                ]);
            }
            $created++;
        }

        $this->info("Bootstrap complete: {$created} new, {$existing} already existed.");
        return self::SUCCESS;
    }
}
```

- [ ] **Step 4: Register command in service provider**

Edit `src/EvoAccessServiceProvider.php`, modify `registerConsoleCommands()`:

```php
private function registerConsoleCommands(): void
{
    $this->commands([
        \Saniock\EvoAccess\Console\BootstrapCommand::class,
        \Saniock\EvoAccess\Console\SyncPermissionsCommand::class,
    ]);
}
```

- [ ] **Step 5: Run tests**

```bash
./vendor/bin/phpunit tests/Feature/BootstrapCommandTest.php
```

- [ ] **Step 6: Commit**

```bash
git add src/Console/BootstrapCommand.php src/EvoAccessServiceProvider.php tests/Feature/BootstrapCommandTest.php
git commit -m "feat(console): BootstrapCommand for superadmin assignment"
```

### Task 8.2: `SyncPermissionsCommand` (verify existing stub)

**Files:**
- Modify: `src/Console/SyncPermissionsCommand.php` (verify)
- Create: `tests/Feature/SyncPermissionsCommandTest.php`

- [ ] **Step 1: Write test**

```php
<?php

namespace Saniock\EvoAccess\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Saniock\EvoAccess\Models\Permission;
use Saniock\EvoAccess\Services\PermissionCatalog;
use Saniock\EvoAccess\Tests\TestCase;

class SyncPermissionsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_persists_in_memory_catalog_to_db(): void
    {
        $catalog = $this->app->make(PermissionCatalog::class);
        $catalog->registerPermissions('orders', [
            ['name' => 'orders.orders', 'label' => 'L', 'actions' => ['view']],
            ['name' => 'orders.payments', 'label' => 'P', 'actions' => ['view', 'update']],
        ]);

        $this->artisan('evoaccess:sync-permissions')
            ->expectsOutputToContain('created 2')
            ->assertSuccessful();

        $this->assertSame(2, Permission::count());
    }
}
```

- [ ] **Step 2: Run test**

The existing stub already has the basic logic. If the test passes immediately, just commit. If not, fix the wording in the output to match.

```bash
./vendor/bin/phpunit tests/Feature/SyncPermissionsCommandTest.php
```

- [ ] **Step 3: Adjust output formatting if needed**

Make sure `src/Console/SyncPermissionsCommand.php` outputs `Sync complete: created N, updated N, orphaned N` matching what the test expects.

- [ ] **Step 4: Commit**

```bash
git add src/Console/SyncPermissionsCommand.php tests/Feature/SyncPermissionsCommandTest.php
git commit -m "test(console): verify SyncPermissionsCommand works end-to-end"
```

### Task 8.3: `MigrateLegacyEvoRolesCommand`

**Files:**
- Create: `src/Console/MigrateLegacyEvoRolesCommand.php`

This command is consumer-specific (it queries `user_attributes` from EVO). It's harder to test in isolation because Testbench doesn't have an `user_attributes` table. The plan is to write the command, manually test with `--dry-run` against the real Ddaudio DB, and skip automated tests.

- [ ] **Step 1: Create the command**

```php
<?php

namespace Saniock\EvoAccess\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Saniock\EvoAccess\Models\Role;
use Saniock\EvoAccess\Models\UserRole;

class MigrateLegacyEvoRolesCommand extends Command
{
    protected $signature = 'evoaccess:migrate-legacy-evo-roles
                            {--mapping= : Path to JSON mapping file (evo_role_id → dd_role_name)}
                            {--dry-run : Print what would be done without making changes}';

    protected $description = 'Migrate users from legacy EVO roles to evoAccess DD roles based on a JSON mapping.';

    public function handle(): int
    {
        $mappingPath = $this->option('mapping');

        if (!$mappingPath || !is_file($mappingPath)) {
            $this->error("Mapping file not found: {$mappingPath}");
            return self::FAILURE;
        }

        $mapping = json_decode(file_get_contents($mappingPath), true);
        if (!is_array($mapping)) {
            $this->error('Mapping file must contain a JSON object: { "evo_role_id": "dd_role_name", ... }');
            return self::FAILURE;
        }

        if (!Schema::hasTable('user_attributes')) {
            $this->error("Table 'user_attributes' not found. This command requires an EVO database.");
            return self::FAILURE;
        }

        $evoUsers = DB::table('user_attributes')
            ->where('role', '>', 0)
            ->get(['internalKey', 'role', 'fullname']);

        $stats = ['migrated' => 0, 'skipped_no_mapping' => 0, 'skipped_already_assigned' => 0];

        foreach ($evoUsers as $evoUser) {
            $evoRoleId = (string) $evoUser->role;
            $ddRoleName = $mapping[$evoRoleId] ?? null;

            if ($ddRoleName === null) {
                $this->warn("Skipping user {$evoUser->internalKey} ({$evoUser->fullname}) — no mapping for EVO role {$evoRoleId}");
                $stats['skipped_no_mapping']++;
                continue;
            }

            $ddRole = Role::where('name', $ddRoleName)->first();
            if (!$ddRole) {
                $this->error("DD role '{$ddRoleName}' does not exist — create it first in /access/matrix");
                continue;
            }

            $existing = UserRole::where('user_id', $evoUser->internalKey)->first();
            if ($existing) {
                $stats['skipped_already_assigned']++;
                continue;
            }

            if (!$this->option('dry-run')) {
                UserRole::create([
                    'user_id'     => $evoUser->internalKey,
                    'role_id'     => $ddRole->id,
                    'assigned_by' => null,
                ]);
            }

            $this->info("✓ {$evoUser->fullname} (user_id={$evoUser->internalKey}) → {$ddRoleName}");
            $stats['migrated']++;
        }

        $this->newLine();
        $this->info("Migration complete: {$stats['migrated']} migrated, {$stats['skipped_no_mapping']} skipped (no mapping), {$stats['skipped_already_assigned']} skipped (already assigned)");

        return self::SUCCESS;
    }
}
```

- [ ] **Step 2: Register the command**

Add to `src/EvoAccessServiceProvider::registerConsoleCommands()`:

```php
\Saniock\EvoAccess\Console\MigrateLegacyEvoRolesCommand::class,
```

- [ ] **Step 3: Verify command is listed**

```bash
./vendor/bin/phpunit tests/Unit/SmokeTest.php
```

(Smoke test still passes — command is registered in container.)

Optionally, run from a Ddaudio context with the package installed:

```bash
php artisan list | grep evoaccess
```

Expected:

```
evoaccess:bootstrap                 Ensure bootstrap superadmin user IDs ...
evoaccess:migrate-legacy-evo-roles  Migrate users from legacy EVO roles ...
evoaccess:sync-permissions          Sync the in-memory permission catalog ...
```

- [ ] **Step 4: Commit**

```bash
git add src/Console/MigrateLegacyEvoRolesCommand.php src/EvoAccessServiceProvider.php
git commit -m "feat(console): MigrateLegacyEvoRolesCommand for one-time legacy import"
```

---

## Phase 9: HTTP layer + admin controllers

The admin UI is mounted under `/access/`. Routes are defined in `src/Http/routes.php`. Each section has a controller; views are simple Bootstrap 5 + vanilla JS blade templates (originally planned as Webix — see Phase 10 implementation note for the rationale of the change).

### Task 9.1: Routes file + BaseController

**Files:**
- Modify: `src/Http/routes.php`
- Create: `src/Controllers/BaseController.php`

- [ ] **Step 1: Write the routes file**

Replace `src/Http/routes.php`:

```php
<?php

use Illuminate\Support\Facades\Route;
use Saniock\EvoAccess\Controllers\AuditController;
use Saniock\EvoAccess\Controllers\MatrixController;
use Saniock\EvoAccess\Controllers\RolesController;
use Saniock\EvoAccess\Controllers\UsersController;

Route::group([
    'prefix'     => 'access',
    'as'         => 'evoAccess.',
    'middleware' => ['web'],
], function () {
    // Roles CRUD
    Route::get('roles', [RolesController::class, 'index'])->name('roles.index');
    Route::post('roles', [RolesController::class, 'store'])->name('roles.store');
    Route::put('roles/{id}', [RolesController::class, 'update'])->name('roles.update');
    Route::delete('roles/{id}', [RolesController::class, 'destroy'])->name('roles.destroy');
    Route::post('roles/{id}/clone', [RolesController::class, 'clone'])->name('roles.clone');

    // Matrix
    Route::get('matrix', [MatrixController::class, 'index'])->name('matrix.index');
    Route::get('matrix/data/{role_id}', [MatrixController::class, 'data'])->name('matrix.data');
    Route::post('matrix/grant', [MatrixController::class, 'grant'])->name('matrix.grant');
    Route::delete('matrix/revoke', [MatrixController::class, 'revoke'])->name('matrix.revoke');

    // Users
    Route::get('users', [UsersController::class, 'index'])->name('users.index');
    Route::get('users/search', [UsersController::class, 'search'])->name('users.search');
    Route::get('users/{user_id}/effective', [UsersController::class, 'effective'])->name('users.effective');
    Route::post('users/{user_id}/assign', [UsersController::class, 'assign'])->name('users.assign');
    Route::post('users/{user_id}/overrides', [UsersController::class, 'addOverride'])->name('users.overrides.add');
    Route::delete('users/{user_id}/overrides/{override_id}', [UsersController::class, 'removeOverride'])->name('users.overrides.remove');

    // Audit
    Route::get('audit', [AuditController::class, 'index'])->name('audit.index');
    Route::get('audit/data', [AuditController::class, 'data'])->name('audit.data');
});
```

- [ ] **Step 2: Create `BaseController` with auth gate**

Create `src/Controllers/BaseController.php`:

```php
<?php

namespace Saniock\EvoAccess\Controllers;

use Illuminate\Routing\Controller;
use Saniock\EvoAccess\Exceptions\AccessDeniedException;
use Saniock\EvoAccess\Services\AccessService;

abstract class BaseController extends Controller
{
    public function __construct(
        protected readonly AccessService $access,
    ) {
        $this->ensureAdminAccess();
    }

    /**
     * The Access admin UI is gated by its own permission `access.admin` action `view`.
     * Superadmin (is_system=1) bypasses everything via the resolver short-circuit.
     */
    private function ensureAdminAccess(): void
    {
        $userId = $this->currentUserId();

        if ($userId === 0) {
            abort(401, 'Login required.');
        }

        if (!$this->access->can('access.admin', 'view', $userId)) {
            throw new AccessDeniedException(
                'Access denied — you do not have permission to manage access control.',
                permission: 'access.admin',
                action: 'view',
                userId: $userId,
            );
        }
    }

    protected function currentUserId(): int
    {
        if (function_exists('evo')) {
            return (int) evo()->getLoginUserID('mgr');
        }
        return 0;
    }
}
```

- [ ] **Step 3: Register the `access.admin` permission inside the package itself**

Edit `src/EvoAccessServiceProvider::boot()`, add at the end (after other boot logic):

```php
$this->app->make(\Saniock\EvoAccess\Services\PermissionCatalog::class)
    ->registerPermissions('access', [
        [
            'name'    => 'access.admin',
            'label'   => 'Access — administration',
            'actions' => ['view', 'update'],
        ],
    ]);
```

- [ ] **Step 4: Commit**

```bash
git add src/Http/routes.php src/Controllers/BaseController.php src/EvoAccessServiceProvider.php
git commit -m "feat(http): routes file + BaseController auth gate + access.admin permission"
```

### Task 9.2: `RolesController` — CRUD for roles

**Files:**
- Create: `src/Controllers/RolesController.php`

- [ ] **Step 1: Create the controller**

```php
<?php

namespace Saniock\EvoAccess\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Saniock\EvoAccess\Models\Role;
use Saniock\EvoAccess\Models\RolePermissionAction;

class RolesController extends BaseController
{
    public function index(): JsonResponse
    {
        $roles = Role::query()
            ->withCount('userAssignments')
            ->orderBy('name')
            ->get(['id', 'name', 'label', 'description', 'is_system', 'created_at', 'updated_at']);

        return response()->json($roles);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'        => 'required|string|max:64|regex:/^[a-z][a-z0-9_]*$/|unique:ea_roles,name',
            'label'       => 'required|string|max:128',
            'description' => 'nullable|string|max:255',
        ]);

        $role = Role::create($data + ['is_system' => false]);

        return response()->json($role, 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $role = Role::findOrFail($id);

        if ($role->is_system) {
            return response()->json(['error' => 'System role cannot be modified'], 403);
        }

        $data = $request->validate([
            'label'       => 'sometimes|required|string|max:128',
            'description' => 'sometimes|nullable|string|max:255',
        ]);

        $role->update($data);

        return response()->json($role);
    }

    public function destroy(int $id): JsonResponse
    {
        $role = Role::findOrFail($id);

        if ($role->is_system) {
            return response()->json(['error' => 'System role cannot be deleted'], 403);
        }

        try {
            $role->delete();
        } catch (\Illuminate\Database\QueryException $e) {
            return response()->json(['error' => 'Cannot delete role with assigned users — reassign them first'], 409);
        }

        return response()->json(['ok' => true]);
    }

    public function clone(int $id): JsonResponse
    {
        $source = Role::findOrFail($id);

        $newName = $source->name . '_copy';
        $i = 1;
        while (Role::where('name', $newName)->exists()) {
            $i++;
            $newName = $source->name . '_copy_' . $i;
        }

        $newRole = Role::create([
            'name'        => $newName,
            'label'       => $source->label . ' (copy)',
            'description' => $source->description,
            'is_system'   => false,
        ]);

        // Copy all grants
        $sourceGrants = RolePermissionAction::where('role_id', $source->id)->get();
        foreach ($sourceGrants as $grant) {
            RolePermissionAction::create([
                'role_id'       => $newRole->id,
                'permission_id' => $grant->permission_id,
                'action'        => $grant->action,
            ]);
        }

        return response()->json($newRole, 201);
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add src/Controllers/RolesController.php
git commit -m "feat(http): RolesController with full CRUD + clone"
```

### Task 9.3: `MatrixController` + `UsersController` + `AuditController`

**Files:**
- Create: `src/Controllers/MatrixController.php`
- Create: `src/Controllers/UsersController.php`
- Create: `src/Controllers/AuditController.php`

- [ ] **Step 1: Create `MatrixController`**

```php
<?php

namespace Saniock\EvoAccess\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Saniock\EvoAccess\Models\RolePermissionAction;
use Saniock\EvoAccess\Services\PermissionCatalog;

class MatrixController extends BaseController
{
    public function index()
    {
        return view('evoAccess::matrix');
    }

    public function data(int $role_id, PermissionCatalog $catalog): JsonResponse
    {
        $grants = RolePermissionAction::where('role_id', $role_id)
            ->get()
            ->groupBy('permission_id');

        return response()->json([
            'permissions' => $catalog->all(),
            'grants'      => $grants,
        ]);
    }

    public function grant(Request $request): JsonResponse
    {
        $data = $request->validate([
            'role_id'       => 'required|integer|exists:ea_roles,id',
            'permission_id' => 'required|integer|exists:ea_permissions,id',
            'action'        => 'required|string|max:32',
        ]);

        RolePermissionAction::firstOrCreate([
            'role_id'       => $data['role_id'],
            'permission_id' => $data['permission_id'],
            'action'        => $data['action'],
        ]);

        return response()->json(['ok' => true]);
    }

    public function revoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'role_id'       => 'required|integer',
            'permission_id' => 'required|integer',
            'action'        => 'required|string|max:32',
        ]);

        RolePermissionAction::where($data)->delete();

        return response()->json(['ok' => true]);
    }
}
```

- [ ] **Step 2: Create `UsersController`**

```php
<?php

namespace Saniock\EvoAccess\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Saniock\EvoAccess\Models\Permission;
use Saniock\EvoAccess\Models\UserOverride;
use Saniock\EvoAccess\Models\UserRole;
use Saniock\EvoAccess\Services\PermissionResolver;

class UsersController extends BaseController
{
    public function index()
    {
        return view('evoAccess::users');
    }

    public function search(Request $request): JsonResponse
    {
        $q = $request->input('q', '');

        if (!\Schema::hasTable('user_attributes')) {
            return response()->json([]);
        }

        $users = DB::table('user_attributes')
            ->where('role', '>', 0)
            ->where(function ($query) use ($q) {
                $query->where('fullname', 'like', "%{$q}%")
                    ->orWhere('internalKey', 'like', "%{$q}%");
            })
            ->limit(20)
            ->get(['internalKey as user_id', 'fullname']);

        return response()->json($users);
    }

    public function effective(int $user_id, PermissionResolver $resolver): JsonResponse
    {
        $userRole = UserRole::where('user_id', $user_id)->first();
        $map = $resolver->loadForUser($user_id);
        $overrides = UserOverride::where('user_id', $user_id)
            ->with('permission')
            ->get();

        return response()->json([
            'user_id'   => $user_id,
            'role_id'   => $userRole?->role_id,
            'effective' => $map,
            'overrides' => $overrides,
        ]);
    }

    public function assign(Request $request, int $user_id): JsonResponse
    {
        $data = $request->validate(['role_id' => 'required|integer|exists:ea_roles,id']);

        UserRole::updateOrCreate(
            ['user_id' => $user_id],
            ['role_id' => $data['role_id']],
        );

        return response()->json(['ok' => true]);
    }

    public function addOverride(Request $request, int $user_id): JsonResponse
    {
        $data = $request->validate([
            'permission_id' => 'required|integer|exists:ea_permissions,id',
            'action'        => 'required|string|max:32',
            'mode'          => 'required|in:grant,revoke',
            'reason'        => 'required|string|max:255',
        ]);

        // Remove conflicting override (PK doesn't include mode)
        UserOverride::where([
            'user_id'       => $user_id,
            'permission_id' => $data['permission_id'],
            'action'        => $data['action'],
        ])->delete();

        UserOverride::create($data + ['user_id' => $user_id]);

        return response()->json(['ok' => true], 201);
    }

    public function removeOverride(int $user_id, int $override_id): JsonResponse
    {
        UserOverride::where('user_id', $user_id)
            ->where('id', $override_id)  // assuming we add an id later, or compose from PK
            ->delete();

        return response()->json(['ok' => true]);
    }
}
```

- [ ] **Step 3: Create `AuditController`**

```php
<?php

namespace Saniock\EvoAccess\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Saniock\EvoAccess\Services\AuditLogger;

class AuditController extends BaseController
{
    public function index()
    {
        return view('evoAccess::audit');
    }

    public function data(Request $request, AuditLogger $audit): JsonResponse
    {
        $filters = $request->only(['actor_user_id', 'target_user_id', 'target_role_id', 'action', 'from', 'to']);
        $limit = (int) ($request->input('limit', 100));
        $offset = (int) ($request->input('offset', 0));

        return response()->json($audit->search($filters, $limit, $offset));
    }
}
```

- [ ] **Step 4: Commit**

```bash
git add src/Controllers/MatrixController.php src/Controllers/UsersController.php src/Controllers/AuditController.php
git commit -m "feat(http): Matrix/Users/Audit controllers"
```

---

## Phase 10: Admin UI views (Bootstrap 5 + vanilla JS)

This phase creates Blade templates that render the admin UI using **Bootstrap 5 (MIT) + vanilla JS** loaded from jsDelivr CDN. No JavaScript framework, no jQuery, no build step. The views are simple reference implementations — most logic is on the server side (controllers + AJAX endpoints).

> **Implementation note (post-execution):** This phase originally targeted Webix Standard (GPL). After the views were written, the choice was reversed to Bootstrap 5 because Webix Standard's GPL caascade would force every consumer project into GPL — incompatible with proprietary projects that want to consume the package — and because consumer projects already on Webix Pro (e.g. Ddaudio) would clash with Webix Standard loaded from CDN. The actual final views in `views/` (commit `9303548`) are the Bootstrap 5 + vanilla JS implementation. Consumer projects override these via `vendor:publish --tag=evo-access-views` and rewrite them with their own UI framework. See design.md §10.6 for the full UI override pattern.
>
> **The Webix-based code shown below is preserved as historical context for the original plan.** The Bootstrap 5 versions in `views/*.blade.php` are the source of truth for the current package state.

### Task 10.1: Layout + 4 view templates

**Files:**
- Create: `views/layout.blade.php`
- Create: `views/roles.blade.php`
- Create: `views/matrix.blade.php`
- Create: `views/users.blade.php`
- Create: `views/audit.blade.php`

> **Note:** This task creates intentionally minimal views — enough to render the admin sections with Webix datatables. The full visual polish (matching the v3 mockup from the brainstorm) is iterated upon during smoke testing in Phase 12.

- [ ] **Step 1: Create `views/layout.blade.php`**

```blade
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>EvoAccess — @yield('title', 'Access')</title>
    <link rel="stylesheet" href="//cdn.webix.com/edge/webix.css">
    <script src="//cdn.webix.com/edge/webix.js"></script>
    <style>
        html, body { height: 100%; margin: 0; }
        .ea-sidebar { background: #f8fafc; }
        .ea-active { background: #3b82f6 !important; color: #fff !important; }
    </style>
</head>
<body>
<div id="ea-app"></div>
<script>
    const EVO_ACCESS_BASE = '{{ url('access') }}';
    @yield('script')
</script>
</body>
</html>
```

- [ ] **Step 2: Create `views/matrix.blade.php`**

```blade
@extends('evoAccess::layout')

@section('title', 'Permission Matrix')

@section('script')
webix.ready(function () {
    webix.ui({
        container: 'ea-app',
        type: 'space',
        rows: [
            { template: 'EvoAccess — Matrix', height: 50, css: 'ea-active' },
            {
                cols: [
                    {
                        view: 'list',
                        id: 'rolesList',
                        width: 220,
                        css: 'ea-sidebar',
                        template: '#label# (#user_count#)',
                        url: EVO_ACCESS_BASE + '/roles',
                        on: {
                            onAfterSelect: function (id) {
                                webix.ajax(EVO_ACCESS_BASE + '/matrix/data/' + id, function (text) {
                                    const data = JSON.parse(text);
                                    $$('matrixGrid').clearAll();
                                    $$('matrixGrid').parse(data.permissions);
                                });
                            }
                        }
                    },
                    {
                        view: 'datatable',
                        id: 'matrixGrid',
                        columns: [
                            { id: 'name',  header: 'Permission', fillspace: 2 },
                            { id: 'label', header: 'Description', fillspace: 3 }
                        ]
                    }
                ]
            }
        ]
    });
});
@endsection
```

- [ ] **Step 3: Create `views/roles.blade.php`**

```blade
@extends('evoAccess::layout')
@section('title', 'Roles')

@section('script')
webix.ready(function () {
    webix.ui({
        container: 'ea-app',
        view: 'datatable',
        url: EVO_ACCESS_BASE + '/roles',
        columns: [
            { id: 'name',        header: 'Name',        fillspace: 1 },
            { id: 'label',       header: 'Label',       fillspace: 2 },
            { id: 'description', header: 'Description', fillspace: 3 },
            { id: 'is_system',   header: 'System',      width: 80 }
        ]
    });
});
@endsection
```

- [ ] **Step 4: Create `views/users.blade.php`**

```blade
@extends('evoAccess::layout')
@section('title', 'Users')

@section('script')
webix.ready(function () {
    webix.ui({
        container: 'ea-app',
        rows: [
            { view: 'text', id: 'userSearch', placeholder: 'Search by name or user_id...' },
            { view: 'list', id: 'userList', template: '#fullname# (id #user_id#)' }
        ]
    });
});
@endsection
```

- [ ] **Step 5: Create `views/audit.blade.php`**

```blade
@extends('evoAccess::layout')
@section('title', 'Audit Log')

@section('script')
webix.ready(function () {
    webix.ui({
        container: 'ea-app',
        view: 'datatable',
        url: EVO_ACCESS_BASE + '/audit/data',
        columns: [
            { id: 'created_at',    header: 'Time',     width: 160 },
            { id: 'actor_user_id', header: 'Actor',    width: 80  },
            { id: 'action',        header: 'Action',   width: 140 },
            { id: 'target_role_id',header: 'Role',     width: 60  },
            { id: 'target_user_id',header: 'User',     width: 60  },
            { id: 'permission_id', header: 'Perm',     width: 60  },
            { id: 'old_value',     header: 'Old',      fillspace: 1 },
            { id: 'new_value',     header: 'New',      fillspace: 1 }
        ]
    });
});
@endsection
```

- [ ] **Step 6: Commit**

```bash
git add views/
git commit -m "feat(views): minimal admin UI templates (layout + roles/matrix/users/audit)"
```

---

## Phase 11: EVO manager menu integration

### Task 11.1: `evoAccessPlugin.php` — inject menu item

**Files:**
- Modify: `plugins/evoAccessPlugin.php`

- [ ] **Step 1: Replace plugin stub with real menu inject**

```php
<?php

use Illuminate\Support\Facades\Event;

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
```

- [ ] **Step 2: Commit**

```bash
git add plugins/evoAccessPlugin.php
git commit -m "feat(plugin): inject Access menu item into EVO manager top menu"
```

---

## Phase 12: Polish + smoke test

### Task 12.1: Update README

**Files:**
- Modify: `README.md`

- [ ] **Step 1: Verify README has install instructions, quick start, license**

The README was already updated during the brand-scrubbing pass. Verify it includes:
- Installation via path repo (composer.json snippet)
- `php artisan vendor:publish --tag=evo-access-config`
- `php artisan migrate`
- `php artisan evoaccess:bootstrap`
- Quick start with `EvoAccess::registerPermissions()` and `EvoAccess::can()`

If anything is missing, add it.

- [ ] **Step 2: Commit if changed**

```bash
git add README.md
git commit -m "docs: polish README with full install + quick start" || echo "no changes"
```

### Task 12.2: Manual smoke test checklist

This task is **manual** — performed by the developer before tagging v0.1.0. There is no automation. Each item is verified by clicking through the EVO manager.

**Files:**
- (none — this is a procedure)

- [ ] **Step 1: Install package in Ddaudio via path repo**

In `core/custom/composer.json`:

```json
"repositories": [{ "type": "path", "url": "packages/evo-access", "options": { "symlink": true } }],
"require": { "saniock/evo-access": "@dev" }
```

```bash
cd core/custom && composer require saniock/evo-access:@dev
```

Expected: package symlinked into `vendor/saniock/evo-access/`.

- [ ] **Step 2: Publish config + run migrations**

```bash
php artisan vendor:publish --tag=evo-access-config
php artisan migrate
```

Expected: 6 `ea_*` tables created in the database, `superadmin` row seeded.

- [ ] **Step 3: Edit bootstrap config + run bootstrap**

Edit `core/custom/config/access/bootstrap.php`:

```php
return ['superadmin_user_ids' => [7]];
```

```bash
php artisan evoaccess:bootstrap
```

Expected: `Bootstrap complete: 1 new, 0 already existed.`

- [ ] **Step 4: Sync the package's own permissions**

```bash
php artisan evoaccess:sync-permissions
```

Expected: `Sync complete: created 1, updated 0, orphaned 0` (the `access.admin` permission registered by the package itself).

- [ ] **Step 5: Open `/access/matrix` in EVO manager**

Log into the EVO manager as user 7. Open `/access/matrix`.

Expected:
- Page loads without 403
- Sidebar shows `superadmin` role
- Selecting it shows the `access.admin` permission

- [ ] **Step 6: Tag v0.1.0**

If all checks pass:

```bash
cd core/custom/packages/evo-access
git tag v0.1.0
```

(No push to remote — local-only per the user's preference until everything is tested.)

---

## Self-review checklist

After completing all tasks above, verify:

### Spec coverage

- ✅ §5 (Data model): all 6 migrations in Phase 1
- ✅ §6 (Service contracts): AccessService, PermissionCatalog, PermissionResolver, AuditLogger in Phases 3-6
- ✅ §7 (Resolver algorithm): 16-case truth table tests in Task 4.3
- ✅ §8 (Permission catalog format): registerPermissions + validation in Task 3.1
- ✅ §9 (Cache + observers): Phases 4 (cache) + 7 (observers)
- ✅ §10 (Admin UI): Phases 9 + 10
- ✅ §11 (Bootstrap): Task 8.1
- ✅ §12 (Migration from legacy): Task 8.3
- ✅ §13 (Rollout plan): mapped to atomic commits throughout

### Type consistency

- All service classes use `Saniock\EvoAccess\` namespace
- All table names match `ea_*` prefix from the design
- All method signatures match between contracts (`AccessServiceInterface`, `PermissionCatalogInterface`) and implementations

### What this plan does NOT cover

- **Phase B (Ddaudio consumer migration)** — written as a separate plan after Phase A is complete and tested
- **CI/CD setup** — out of scope for v0.1.0; manual testing is sufficient for local development
- **Packagist publication** — deferred per user preference; only path-repo dev for now
- **Localised UI** — views are English-only at first; Ukrainian translations come during real-world use

---

## Execution handoff

Plan complete and saved to `core/custom/packages/evo-access/docs/plan.md`. Two execution options:

**1. Subagent-Driven (recommended)** — I dispatch a fresh subagent per task with two-stage review (spec compliance + code quality), fast iteration in this session.

**2. Inline Execution** — Execute tasks in this session using executing-plans, batch execution with manual checkpoints between tasks.

**Which approach?**
