# EvoAccess — Design Document

| | |
|---|---|
| **Package** | `saniock/evo-access` |
| **Namespace** | `Saniock\EvoAccess\` |
| **Version** | 0.1.0 (draft, pre-implementation) |
| **Status** | Approved design — implementation pending |
| **Author** | Oleksandr (saniock) |
| **Date** | 2026-04-08 |
| **License** | GPL-3.0-or-later |

---

## 1. Purpose

EvoAccess is a role-and-permission management package for **Evolution CMS 3** custom modules. It provides a centralised, auditable, database-backed access-control layer that replaces ad-hoc per-module hardcoded user-id arrays, scattered `$_SESSION['mgrRole']` checks, and hand-edited EVO plugin files.

It is built as a self-contained, reusable composer package consumable by any EVO 3 project — not just one specific application.

---

## 2. Background and motivation

Evolution CMS 3 ships with a basic role system on the `user_attributes.role` column. For simple installations this is sufficient: a few EVO native modules with `properties.roles` declarations and the role check is enough.

In practice, on long-lived projects the system breaks down. A typical large EVO project ends up with **three or four parallel access-control mechanisms** layered on top of each other:

1. **EVO native role** — `$_SESSION['mgrRole']` checks scattered across module BaseControllers
2. **Hardcoded `managerAccess = [...]` arrays** — copy-pasted lists of EVO user IDs in many module BaseControllers, each maintained independently
3. **Per-user override plugins** — files like `plugin.ManagerAccess.php` containing hardcoded `$userRoles[X] = [...]` blocks for individual people, with comments like `// Manager Name`
4. **`properties.roles` / `properties.users`** on EVO native modules — yet another layer of role-id-to-module mapping

This sprawl makes the system unmaintainable:

- Adding a new module requires editing several files in different places
- A manager changing job requires editing a hardcoded plugin file and redeploying
- A new role can't really be added — only by editing PHP code
- There is **no audit trail** for who was given access when or why
- There is **no way to query** "what can this user actually do?" without tracing through several files
- Removing a person's access is fragile because their IDs are scattered

EvoAccess solves this by introducing a single, database-backed, observable access-control layer with a clean public API and a built-in admin UI.

---

## 3. Goals and non-goals

### Goals

- **Centralisation.** All access decisions for custom modules go through one service.
- **Granularity.** Per-permission, per-action access. A role can have `view + update` on one section but only `view` on another.
- **Custom actions.** Modules declare their own action vocabulary; no fixed enum.
- **Per-user overrides.** Both grant (extend role) and revoke (restrict role) are first-class citizens with required `reason`.
- **Auditability.** Every change is recorded in an immutable audit log with actor, target, timestamp, and structured details.
- **Self-contained.** Works in any EVO 3 project; not coupled to any specific consumer.
- **Decoupled from EVO native role.** EVO `user_attributes.role > 0` is treated only as a login gate ("can this user enter the manager at all"); EvoAccess maintains its own role assignments independently.
- **Safe rollout.** Coexists with the legacy system during migration; consumer modules can be migrated one at a time.
- **No self-lockout.** A system superadmin role bypasses the permission matrix entirely; bootstrap config guarantees at least one user always has it.

### Non-goals

- **Replacing EVO native roles.** Existing EVO admin functionality (role-restricted document operations, native module visibility) continues to work as before.
- **Role hierarchy / inheritance.** Roles are flat. Cloning is provided as the alternative to inheritance.
- **Multi-role per user.** One user has exactly one role. Per-user overrides cover the cases where multi-role would be needed.
- **Cross-package permission interop.** EvoAccess does not try to standardise with other access packages (Spatie permissions, Bouncer, etc).
- **Permission management for legacy EVO native modules.** Those continue to use `properties.roles`. EvoAccess governs only custom modules that opt in.

---

## 4. Architecture overview

EvoAccess is composed of four service classes, six Eloquent models, four observers, one console command, an admin UI, and an EVO manager-menu plugin. All wired through a single Laravel service provider.

```
┌────────────────────────────────────────────────────────────────────┐
│                       Consumer project (e.g. Ddaudio)              │
│                                                                    │
│  ┌──────────────────┐  ┌──────────────────┐  ┌──────────────────┐  │
│  │ Module A         │  │ Module B         │  │ Module C         │  │
│  │ BaseController   │  │ BaseController   │  │ BaseController   │  │
│  └────────┬─────────┘  └────────┬─────────┘  └────────┬─────────┘  │
└───────────┼─────────────────────┼─────────────────────┼────────────┘
            │                     │                     │
            └─────────────────────┴─────────────────────┘
                                  │
                                  ▼
                ┌──────────────────────────────────┐
                │       EvoAccess Facade           │
                │  EvoAccess::can(...)             │
                │  EvoAccess::canView(...)         │
                │  EvoAccess::canEdit(...)         │
                │  EvoAccess::filterMenu(...)      │
                │  EvoAccess::actionsFor(...)      │
                │  EvoAccess::registerPermissions  │
                └────────────────┬─────────────────┘
                                 │
                                 ▼
                ┌──────────────────────────────────┐
                │         AccessService            │
                │   (public, the source of truth   │
                │    for any "can X do Y?" query)  │
                └──┬────────────────────────────┬──┘
                   │                            │
        ┌──────────▼─────────┐    ┌─────────────▼────────┐
        │  PermissionCatalog │    │   PermissionResolver │
        │  in-memory         │    │   per-request cache  │
        │  registry          │    │   resolution algo    │
        └────────────────────┘    └──────────┬───────────┘
                                             │
                                             ▼
                                  ┌──────────────────────────────┐
                                  │           Database           │
                                  │  ea_roles                    │
                                  │  ea_permissions              │
                                  │  ea_role_permission_actions  │
                                  │  ea_user_roles               │
                                  │  ea_user_overrides           │
                                  │  ea_audit_log                │
                                  └─────────────┬────────────────┘
                                            ▲
                                            │ writes
                            ┌───────────────┴────────────┐
                            │ Eloquent Observers         │
                            │ - RoleObserver             │
                            │ - RolePermissionAction…    │
                            │ - UserRoleObserver         │
                            │ - UserOverrideObserver     │
                            └────┬────────────────┬──────┘
                                 │                │
                                 ▼                ▼
                       ┌─────────────────┐  ┌───────────────┐
                       │  AuditLogger    │  │  cache flush  │
                       │  (writes to     │  │  (forgetUser/ │
                       │   ea_audit_log) │  │   forgetAll)  │
                       └─────────────────┘  └───────────────┘
```

The four services:

- **AccessService** — public façade, the only thing consumer code talks to. All permission checks go through it.
- **PermissionCatalog** — in-memory registry. Consumer projects call `registerPermissions()` from their service provider boot to declare what permissions exist in their modules. Synced to the database via a console command.
- **PermissionResolver** — internal worker that computes a user's effective permissions from `ea_user_roles + ea_role_permission_actions + ea_user_overrides`, with per-request caching.
- **AuditLogger** — write-only service that records every permission mutation to `ea_audit_log`. Called from observers, not from controllers directly.

---

## 5. Data model

All tables use the `ea_` prefix (evoAccess), `InnoDB` engine, `utf8mb4_unicode_ci` collation. Six tables in dependency order:

### 5.1 `ea_roles`

Named bundles of permission grants. Roles are flat (no hierarchy). One special role (`superadmin`) is system-flagged and bypasses the permission matrix.

```sql
CREATE TABLE ea_roles (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(64)  NOT NULL,             -- slug, e.g. 'manager'
    label       VARCHAR(128) NOT NULL,             -- human label, e.g. 'Менеджер'
    description VARCHAR(255) NULL,                 -- optional free text
    is_system   TINYINT(1)   NOT NULL DEFAULT 0,   -- 1 for superadmin role
    created_by  INT UNSIGNED NULL,                 -- EVO user.id
    created_at  TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY ea_roles_name_unique (name),
    KEY        ea_roles_is_system_idx (is_system)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seeded on first migration:
INSERT INTO ea_roles (name, label, description, is_system)
VALUES ('superadmin', 'Суперадмін', 'System role with unconditional full access', 1);
```

### 5.2 `ea_permissions`

Catalog of known permissions. Synced from the in-memory `PermissionCatalog` by the `evoaccess:sync-permissions` command.

```sql
CREATE TABLE ea_permissions (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(128) NOT NULL,             -- 'orders.orders'
    label       VARCHAR(255) NOT NULL,             -- 'Order list'
    module      VARCHAR(64)  NOT NULL,             -- 'orders' (UI grouping key)
    actions     JSON         NOT NULL,             -- ["view","update","export"]
    is_orphaned TINYINT(1)   NOT NULL DEFAULT 0,   -- 1 if removed from in-memory catalog
    created_at  TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY ea_permissions_name_unique (name),
    KEY        ea_permissions_module_idx (module),
    KEY        ea_permissions_orphaned_idx (is_orphaned)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**`actions` is denormalized as JSON intentionally.** Permissions are read-mostly, the action set is always read with the permission, and a separate `permission_actions` table would force a JOIN on every catalog read with no real benefit. JSON column is supported by MySQL 8 and can be indexed via generated columns if a future query pattern requires it.

**`is_orphaned`** is set when a permission name disappears from the in-memory catalog (e.g. a developer removed it from `permissions.php`). The row is **never deleted**, only flagged. This preserves historical names so audit log entries referencing old permissions remain resolvable.

### 5.3 `ea_role_permission_actions`

Normalized grant table. Presence of a row means "this role has this action on this permission". One row per granular grant.

```sql
CREATE TABLE ea_role_permission_actions (
    role_id       INT UNSIGNED NOT NULL,
    permission_id INT UNSIGNED NOT NULL,
    action        VARCHAR(32)  NOT NULL,
    granted_by    INT UNSIGNED NULL,                -- EVO user.id
    granted_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (role_id, permission_id, action),
    KEY ea_rpa_permission_idx (permission_id),

    CONSTRAINT ea_rpa_role_fk
        FOREIGN KEY (role_id) REFERENCES ea_roles(id)
        ON DELETE CASCADE,

    CONSTRAINT ea_rpa_permission_fk
        FOREIGN KEY (permission_id) REFERENCES ea_permissions(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Composite PK** `(role_id, permission_id, action)` makes duplicate grants impossible at the database level. Adding a grant is `INSERT`, removing is `DELETE`. There is no boolean column or "active" flag.

### 5.4 `ea_user_roles`

Flat user-to-role assignment. One row per user. `user_id` is the primary key, which enforces "one role per user" at the schema level.

```sql
CREATE TABLE ea_user_roles (
    user_id     INT UNSIGNED NOT NULL PRIMARY KEY,
    role_id     INT UNSIGNED NOT NULL,
    assigned_by INT UNSIGNED NULL,
    assigned_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,

    KEY ea_user_roles_role_idx (role_id),

    CONSTRAINT ea_user_roles_role_fk
        FOREIGN KEY (role_id) REFERENCES ea_roles(id)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**`ON DELETE RESTRICT`** on `role_id` is a deliberate safety net. A role with assigned users cannot be deleted; the admin UI will prompt to reassign users first. This prevents accidental loss of access for a whole group of people.

**No FK on `user_id`** — EvoAccess does not assume the existence or shape of a `users` table. Orphaned `ea_user_roles` rows (after an EVO user is deleted) are tolerated and can be cleaned by a periodic job if desired.

### 5.5 `ea_user_overrides`

Per-user exceptions to the role-assigned permission set. Supports both grant (extend role) and revoke (restrict role).

```sql
CREATE TABLE ea_user_overrides (
    user_id       INT UNSIGNED NOT NULL,
    permission_id INT UNSIGNED NOT NULL,
    action        VARCHAR(32)  NOT NULL,
    mode          ENUM('grant','revoke') NOT NULL,
    reason        VARCHAR(255) NULL,
    created_by    INT UNSIGNED NULL,
    created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (user_id, permission_id, action),
    KEY ea_uo_user_idx (user_id),
    KEY ea_uo_permission_idx (permission_id),

    CONSTRAINT ea_uo_permission_fk
        FOREIGN KEY (permission_id) REFERENCES ea_permissions(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Composite PK** `(user_id, permission_id, action)` — note `mode` is **not** in the PK. A user cannot have a grant and a revoke for the same `(permission, action)` simultaneously. The admin UI enforces a "remove existing override before creating opposite" workflow.

**`reason` is intended to be effectively required at the UI layer.** The schema allows `NULL` to support data-import scenarios where reason might be unknown.

### 5.6 `ea_audit_log`

Immutable history of every permission mutation. Written by `AuditLogger` from observer hooks. Read by the admin UI audit screen.

```sql
CREATE TABLE ea_audit_log (
    id             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    actor_user_id  INT UNSIGNED NOT NULL,
    action         VARCHAR(32)  NOT NULL,            -- 'grant'|'revoke'|'create_role'|...
    target_role_id INT UNSIGNED NULL,
    target_user_id INT UNSIGNED NULL,
    permission_id  INT UNSIGNED NULL,
    old_value      VARCHAR(255) NULL,
    new_value      VARCHAR(255) NULL,
    details        JSON         NULL,
    created_at     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,

    KEY ea_audit_actor_idx   (actor_user_id, created_at),
    KEY ea_audit_role_idx    (target_role_id, created_at),
    KEY ea_audit_user_idx    (target_user_id, created_at),
    KEY ea_audit_action_idx  (action),
    KEY ea_audit_created_idx (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**No foreign keys.** Audit must survive deletion of any role, permission, or user. The application code handles dangling references by displaying "Role #X (deleted)" in the UI.

**`BIGINT id`** because audit grows quickly — a busy admin clicking through the matrix can produce dozens of rows per minute.

### 5.7 Migration order

Six migration files, applied in dependency order:

```
2026_04_08_000001_create_ea_roles_table.php             (+ superadmin seed)
2026_04_08_000002_create_ea_permissions_table.php
2026_04_08_000003_create_ea_role_permission_actions_table.php  (FK on 1, 2)
2026_04_08_000004_create_ea_user_roles_table.php               (FK on 1)
2026_04_08_000005_create_ea_user_overrides_table.php           (FK on 2)
2026_04_08_000006_create_ea_audit_log_table.php                (no FK)
```

---

## 6. Service contracts

All four services are bound as **singletons** in `EvoAccessServiceProvider::register()`. Singletons are necessary because `PermissionCatalog` holds the in-memory registry and `PermissionResolver` holds the per-request cache; both must survive across all calls within one request.

### 6.1 AccessService (public)

Implements `AccessServiceInterface`. The only service consumer code talks to.

```php
class AccessService implements AccessServiceInterface
{
    public function __construct(
        private readonly PermissionCatalog $catalog,
        private readonly PermissionResolver $resolver,
    ) {}

    /** Canonical: can $userId perform $action on $permission? */
    public function can(string $permission, string $action, int $userId): bool;

    /** Throws AccessDeniedException instead of returning false. */
    public function authorize(string $permission, string $action, int $userId): void;

    /** Shortcut: resolve menu item → permission → can(..., 'view', ...). */
    public function canView(array $menu, string $actionId, int $userId): bool;

    /** Shortcut: same as canView but for 'update' action. */
    public function canEdit(array $menu, string $actionId, int $userId): bool;

    /** Returns the menu tree with items the user cannot view removed. */
    public function filterMenu(array $menu, int $userId): array;

    /** Returns ['view'=>true,'update'=>false,...] for blade payloads. */
    public function actionsFor(string $permission, int $userId): array;

    /** Convenience: forwards to PermissionCatalog::registerPermissions(). */
    public function registerPermissions(string $module, array $permissions): void;
}
```

`canView` / `canEdit` / `filterMenu` keep the same signatures as the legacy `EvolutionCMS\Ddaudio\Services\ModuleAccess` class so consumer call-sites can be migrated by changing only the import.

`registerPermissions()` is a thin pass-through to `PermissionCatalog` so consumer code can use a single facade (`EvoAccess::registerPermissions(...)`) for both runtime checks and one-time registration.

### 6.2 PermissionCatalog (public)

Implements `PermissionCatalogInterface`. Consumer projects call `registerPermissions()` from their service provider boot.

```php
class PermissionCatalog implements PermissionCatalogInterface
{
    /** Validates and stores a batch of permissions for a module. */
    public function registerPermissions(string $module, array $permissions): void;

    /** Flat list of all registered permissions. */
    public function all(): array;

    /** Look up by canonical name. */
    public function find(string $name): ?array;

    /** All permissions for one module (UI accordion). */
    public function byModule(string $module): array;

    /** Distinct list of registered modules. */
    public function modules(): array;

    /** UPSERT in-memory catalog into ea_permissions, mark orphans. */
    public function syncToDatabase(): array;  // ['created','updated','orphaned']
}
```

`registerPermissions` validates each row strictly: an `InvalidArgumentException` is thrown immediately on the boot path so configuration errors are surfaced at install time, not at the first runtime check.

### 6.3 PermissionResolver (internal)

Not in the public interface — only `AccessService` consumes it. Holds the per-request cache.

```php
class PermissionResolver
{
    /** @var array<int, array<string, array<string, bool>>> */
    private array $cache = [];

    /** Canonical question. */
    public function userHas(int $userId, string $permission, string $action): bool;

    /** All actions a user can perform on a permission. */
    public function effectiveActions(int $userId, string $permission): array;

    /** Is the user assigned to a system (superadmin) role? */
    public function isSuperadmin(int $userId): bool;

    /** Pre-warm + return the full effective map for a user. */
    public function loadForUser(int $userId): array;

    /** Per-user cache invalidation (called from observers). */
    public function forgetUser(int $userId): void;

    /** Full cache wipe (called on role-grant changes). */
    public function forgetAll(): void;
}
```

The full resolution algorithm is in §7.

### 6.4 AuditLogger (internal API for observers)

Write-mostly. Provides type-safe wrappers over a generic `log()` method.

```php
class AuditLogger
{
    /** Generic write — used directly only for non-standard events. */
    public function log(
        int $actorUserId,
        string $action,
        ?int $targetRoleId = null,
        ?int $targetUserId = null,
        ?int $permissionId = null,
        ?string $oldValue = null,
        ?string $newValue = null,
        array $details = [],
    ): void;

    // Type-safe wrappers (called from observers):
    public function logRoleCreated(int $actorId, Role $role): void;
    public function logRoleRenamed(int $actorId, Role $role, string $oldLabel, string $newLabel): void;
    public function logRoleDeleted(int $actorId, Role $role): void;
    public function logRoleCloned(int $actorId, Role $sourceRole, Role $newRole): void;
    public function logGrant(int $actorId, int $roleId, int $permissionId, string $action): void;
    public function logRevoke(int $actorId, int $roleId, int $permissionId, string $action): void;
    public function logUserAssigned(int $actorId, int $userId, int $roleId): void;
    public function logUserUnassigned(int $actorId, int $userId, int $oldRoleId): void;
    public function logUserRoleChanged(int $actorId, int $userId, int $oldRoleId, int $newRoleId): void;
    public function logOverrideAdded(int $actorId, int $userId, int $permissionId, string $action, string $mode, ?string $reason): void;
    public function logOverrideRemoved(int $actorId, int $userId, int $permissionId, string $action): void;
    public function logPermissionsSync(int $actorId, int $created, int $updated, int $orphaned): void;

    // Read API for the audit UI:
    public function entriesForUser(int $userId, int $limit = 100, int $offset = 0): Collection;
    public function entriesForRole(int $roleId, int $limit = 100, int $offset = 0): Collection;
    public function entriesByActor(int $actorId, int $limit = 100, int $offset = 0): Collection;
    public function search(array $filters, int $limit = 100, int $offset = 0): Collection;
    public function recent(int $limit = 100): Collection;
}
```

Audit writes are **not transactional** with the underlying mutation. A crash between the mutation INSERT and the audit INSERT is theoretically possible but vanishingly unlikely in practice (two consecutive INSERTs into the same database). The simplicity gain outweighs the risk.

---

## 7. Resolver algorithm

The `PermissionResolver::userHas(int $userId, string $permission, string $action): bool` query consults four sources of truth:

| Source | Field | Meaning |
|---|---|---|
| **`is_system`** | `ea_roles.is_system = 1` | The user's role bypasses the matrix |
| **`role grant`** | row in `ea_role_permission_actions` | The role grants this action |
| **`override grant`** | row in `ea_user_overrides` with `mode='grant'` | The user has an explicit per-user grant |
| **`override revoke`** | row in `ea_user_overrides` with `mode='revoke'` | The user has an explicit per-user deny |

### 7.1 Priority order (highest to lowest)

```
1. is_system = 1     → ALLOW  (superadmin short-circuit, ignore everything else)
2. override revoke   → DENY   (explicit deny always wins)
3. override grant    → ALLOW  (explicit grant on top of role)
4. role grant        → ALLOW  (base right from the role)
5. (default)         → DENY   (no data → no access)
```

**Why this order:**

- `is_system` is highest because the superadmin role must work even if the matrix is empty (e.g. fresh install, or after a destructive admin error). It's the safety net against self-lockout.
- `revoke` beats `grant` because explicit denies are safer than explicit allows. A "this manager temporarily cannot refund orders" rule must not silently disappear when the role grants are later expanded.
- `override grant` beats `role grant` because grants are additive — they only matter when adding something the role doesn't already give.
- `default = deny` enforces principle of least privilege. Any unknown state means "no access".

### 7.2 Truth table (all 16 combinations)

| # | `is_system` | `revoke` | `grant` | `role` | → Result |
|---|---|---|---|---|---|
| 1 | 1 | 1 | 1 | 1 | **ALLOW** (is_system) |
| 2 | 1 | 1 | 1 | 0 | **ALLOW** (is_system) |
| 3 | 1 | 1 | 0 | 1 | **ALLOW** (is_system) |
| 4 | 1 | 1 | 0 | 0 | **ALLOW** (is_system) |
| 5 | 1 | 0 | 1 | 1 | **ALLOW** (is_system) |
| 6 | 1 | 0 | 1 | 0 | **ALLOW** (is_system) |
| 7 | 1 | 0 | 0 | 1 | **ALLOW** (is_system) |
| 8 | 1 | 0 | 0 | 0 | **ALLOW** (is_system) |
| 9 | 0 | 1 | 1 | 1 | **DENY** (revoke wins) |
| 10 | 0 | 1 | 1 | 0 | **DENY** (revoke wins) |
| 11 | 0 | 1 | 0 | 1 | **DENY** (revoke wins) |
| 12 | 0 | 1 | 0 | 0 | **DENY** (explicit deny on empty — permanent lock) |
| 13 | 0 | 0 | 1 | 1 | **ALLOW** (both grant) |
| 14 | 0 | 0 | 1 | 0 | **ALLOW** (override grant adds) |
| 15 | 0 | 0 | 0 | 1 | **ALLOW** (role grant only) |
| 16 | 0 | 0 | 0 | 0 | **DENY** (default deny) |

Case 12 is intentional: a `revoke` override for a permission/action that has no current grant acts as a **permanent lock**, protecting against future role expansions accidentally granting access to a user who should never have it.

### 7.3 Implementation

```php
public function userHas(int $userId, string $permission, string $action): bool
{
    $map = $this->loadForUser($userId);

    if (isset($map['__is_system']) && $map['__is_system'] === true) {
        return true;
    }

    return $map[$permission][$action] ?? false;
}

public function loadForUser(int $userId): array
{
    if (isset($this->cache[$userId])) {
        return $this->cache[$userId];
    }

    // Find user's role
    $userRole = DB::table('ea_user_roles')->where('user_id', $userId)->first();
    if (!$userRole) {
        return $this->cache[$userId] = [];
    }

    // Check is_system short-circuit
    $role = DB::table('ea_roles')->where('id', $userRole->role_id)->first();
    if ($role && $role->is_system) {
        return $this->cache[$userId] = ['__is_system' => true];
    }

    // Load role grants
    $roleGrants = DB::table('ea_role_permission_actions as rpa')
        ->join('ea_permissions as p', 'p.id', '=', 'rpa.permission_id')
        ->where('rpa.role_id', $userRole->role_id)
        ->where('p.is_orphaned', 0)
        ->select('p.name as permission', 'rpa.action')
        ->get();

    $map = [];
    foreach ($roleGrants as $row) {
        $map[$row->permission][$row->action] = true;
    }

    // Load user overrides (grants first, then revokes — so revoke wins)
    $overrides = DB::table('ea_user_overrides as uo')
        ->join('ea_permissions as p', 'p.id', '=', 'uo.permission_id')
        ->where('uo.user_id', $userId)
        ->where('p.is_orphaned', 0)
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

**Cost:** 1 cache hit OR 3 SQL queries on first call per user per request. All subsequent calls in the same request are free.

### 7.4 Orphaned permissions

Both queries filter `WHERE p.is_orphaned = 0`. Permissions marked as orphaned (because they were removed from the in-memory catalog after the last sync) are **invisible to the resolver** — neither role grants nor user overrides on those permissions take effect. This is a security-positive choice: a permission removed from the catalog should not silently keep granting access.

If the same permission name is later re-registered, `syncToDatabase()` flips `is_orphaned` back to `0` and the previously stored grants/overrides automatically reactivate.

---

## 8. Permission catalog format

### 8.1 Registration API

Consumer projects register permissions from their service provider's `boot()`:

```php
use Saniock\EvoAccess\Facades\EvoAccess;

EvoAccess::registerPermissions('orders', [
    [
        'name'    => 'orders.orders',
        'label'   => 'Order list',
        'actions' => ['view', 'create', 'update', 'delete', 'refund', 'export'],
    ],
    [
        'name'    => 'orders.payments',
        'label'   => 'Payments',
        'actions' => ['view', 'update', 'refund'],
    ],
    // ...
]);
```

The first argument is the **module slug** (used only for UI grouping). The second is a list of permission rows.

### 8.2 Validation rules

Each row is validated immediately upon registration. Failures throw `\InvalidArgumentException` so misconfiguration surfaces at boot time.

| Field | Rule |
|---|---|
| `module` (1st arg) | `string`, matches `^[a-z][a-z0-9_]*$`, length 1..64 |
| `name` | `string`, matches `^[a-z][a-z0-9_]*\.[a-z0-9_.]+$`, length 1..128, must start with `<module>.` |
| `label` | non-empty `string`, length 1..255 |
| `actions` | non-empty array of strings, each matches `^[a-z][a-z0-9_]*$`, length 1..32, no duplicates within a permission |

### 8.3 Standard action vocabulary

The package recommends but does not enforce:

- `view` — read / list / show
- `create` — add new records
- `update` — modify existing records
- `delete` — remove records
- `export` — export to CSV / external service
- `import` — import from file
- `bulk` — mass operations

Modules are free to declare additional custom actions: `refund`, `approve`, `publish`, `archive`, `cancel`, `adjust`, etc. The catalog records whatever the consumer declares.

### 8.4 Per-module organisation

Each consumer module owns a `config/permissions.php` file that returns the row list:

```php
// modules/Orders/config/permissions.php
return [
    ['name' => 'orders.orders',    'label' => 'Order list', 'actions' => ['view', 'create', 'update', 'delete', 'refund', 'export']],
    ['name' => 'orders.payments',  'label' => 'Payments',   'actions' => ['view', 'update', 'refund']],
    ['name' => 'orders.invoices',  'label' => 'Invoices',   'actions' => ['view', 'create', 'export']],
    ['name' => 'orders.shipments', 'label' => 'Shipments',  'actions' => ['view', 'update']],
    // ...
];
```

The consumer service provider loads each module's file and forwards to the catalog:

```php
private function registerEvoAccessPermissions(): void
{
    $modulesPath = __DIR__ . '/../modules';

    EvoAccess::registerPermissions('orders',     require $modulesPath . '/Orders/config/permissions.php');
    EvoAccess::registerPermissions('finances',   require $modulesPath . '/Finances/config/permissions.php');
    EvoAccess::registerPermissions('analytics',  require $modulesPath . '/Analytics/config/permissions.php');
    // ... one line per consumer module
}
```

### 8.5 Sync to database

```bash
php artisan evoaccess:sync-permissions
```

Reads the in-memory catalog, UPSERTs into `ea_permissions` by `name`, marks rows missing from the catalog as `is_orphaned = 1`. Idempotent. Wrapped in a transaction to avoid intermediate states.

After sync, the resolver cache is fully flushed (`forgetAll()`) because orphan flags may have changed.

### 8.6 Connecting permissions to menu items

The menu config (the existing `config/config.php` in each Ddaudio module) references permissions by name in each menu item:

```php
return [
    'menu' => [
        [
            'id' => 'orders',
            'title' => 'Orders',
            'items' => [
                ['id' => 'orders',    'title' => 'Order list', 'permission' => 'orders.orders'],
                ['id' => 'payments',  'title' => 'Payments',   'permission' => 'orders.payments'],
                ['id' => 'invoices',  'title' => 'Invoices',   'permission' => 'orders.invoices'],
                ['id' => 'shipments', 'title' => 'Shipments',  'permission' => 'orders.shipments'],
            ],
        ],
    ],
];
```

The `permission` key on each menu item is the canonical permission name. `AccessService::canView()` looks up the menu item by `id`, reads the `permission` key, and runs `can($permission, 'view', $userId)`.

---

## 9. Cache strategy and observer wiring

### 9.1 Cache layers

| Layer | Storage | Lifetime | Invalidation |
|---|---|---|---|
| **L1: per-request** | `PermissionResolver::$cache` array property | Single request (singleton dies on response) | Automatic on response, plus `forgetUser`/`forgetAll` |
| **L2: optional Redis** (future) | Laravel Cache facade with tags | TTL-based | Tag flush on observer events |

L2 is **not in v1**. The observer wiring is the same; only the resolver implementation needs to be wrapped.

### 9.2 Observer responsibilities

Four Eloquent observers, one per mutable model. All registered in `EvoAccessServiceProvider::boot()`:

```php
Role::observe(RoleObserver::class);
RolePermissionAction::observe(RolePermissionActionObserver::class);
UserRole::observe(UserRoleObserver::class);
UserOverride::observe(UserOverrideObserver::class);
```

| Model | Event | Audit call | Cache flush |
|---|---|---|---|
| `Role` | `created` | `logRoleCreated` | — (no users yet) |
| `Role` | `updated` (label) | `logRoleRenamed` | — (does not affect rights) |
| `Role` | `updated` (is_system) | `log()` generic | `forgetAll()` |
| `Role` | `deleted` | `logRoleDeleted` | `forgetAll()` |
| `RolePermissionAction` | `created` | `logGrant` | `forgetAll()` |
| `RolePermissionAction` | `deleted` | `logRevoke` | `forgetAll()` |
| `UserRole` | `created` | `logUserAssigned` | `forgetUser($userId)` |
| `UserRole` | `updated` | `logUserRoleChanged` | `forgetUser($userId)` |
| `UserRole` | `deleted` | `logUserUnassigned` | `forgetUser($userId)` |
| `UserOverride` | `created` | `logOverrideAdded` | `forgetUser($userId)` |
| `UserOverride` | `deleted` | `logOverrideRemoved` | `forgetUser($userId)` |

Role-level changes (`RolePermissionAction`) trigger `forgetAll()` because the resolver does not know which users currently hold the affected role without an extra SELECT. Bulk-flushing the in-memory cache is cheaper than that lookup, and the cache is per-request anyway, so the cost is negligible.

### 9.3 Resolving the actor

Observers determine the actor (the EVO user who triggered the change) by reading `evo()->getLoginUserID('mgr')` directly. This couples observers to EVO globals, which is acceptable because the package itself targets EVO. CLI invocations (sync command, seeders) return `0`, which is rendered as "System / CLI" in the audit UI.

---

## 10. Admin UI

The package ships with a built-in admin UI mounted under `/access/`. The UI is gated by the `access.admin` permission, which is registered by the package itself and granted to the `superadmin` role by default.

### 10.1 Sections

Four sections accessible from a left sidebar:

1. **Roles** — list of all roles, with create / clone / rename / delete actions.
2. **Matrix** — the main editing screen (see §10.2).
3. **Users** — search a manager, view their effective permissions, manage per-user overrides.
4. **Audit log** — a chronological feed of every change with actor, target, action, and detail filters.

### 10.2 Permission matrix layout

The matrix is the primary working surface for the superadmin. Layout: a left **sidebar of roles** + a right **main area showing the selected role's permissions** grouped by module in collapsible accordions.

```
┌─────────────────┬──────────────────────────────────────────────────┐
│ ROLES           │  Permissions: manager                            │
│ ──────────────  │  Customer order operations + read-only finance   │
│ • superadmin 🔒 │  ┌──────────────────────────────────────────────┐│
│ • manager (8)   │  │ 🔍 Search permission...   [All|Granted|Empty]││
│ • warehouse (3) │  └──────────────────────────────────────────────┘│
│ • finance (2)   │  ▼ Orders                       3 of 4 granted  │
│ • marketing (2) │     ┌─────────────────┬─────┬──────┬──────┐     │
│ • content (1)   │     │                 │view │update│export│     │
│ • viewer (4)    │     ├─────────────────┼─────┼──────┼──────┤     │
│                 │     │ Order list      │  ✓  │  ✓   │  —   │     │
│ + Create role   │     │ Payments        │  ✓  │  ✓   │  ☐   │     │
│ ⧉ Clone         │     │ Invoices        │  ✓  │  ☐   │  ☐   │     │
│ ✎ Rename        │     │ Shipments       │  ☐  │  ☐   │  —   │     │
│ 🗑 Delete       │     └─────────────────┴─────┴──────┴──────┘     │
│                 │  ▶ Finances                     2 of 6 granted  │
│                 │  ▶ Analytics                    0 of 9 granted  │
│                 │  ▶ Warehouses                   1 of 5 granted  │
│                 ├──────────────────────────────────────────────────┤
│                 │  manager · 10 perms · 22 actions · 8 users       │
│                 │  Created 12.03.2026 · Last edit: 5 min ago    ✓  │
└─────────────────┴──────────────────────────────────────────────────┘
```

**Sidebar (left):**

- List of all roles, sorted alphabetically
- Each row shows the role label and a small badge with the count of users assigned (`(8)`)
- The system role (superadmin) is locked (🔒) and cannot be edited
- Below the list: action buttons — Create new role, Clone current, Rename, Delete

**Main area (right):**

- Header: role name and description (editable inline)
- Search bar with quick filters: All / Only granted / Only empty
- Accordion sections per module, each showing a count of granted permissions like `3 of 8 granted` (colour-coded: green=full, yellow=partial, red=none)
- Inside each accordion: a table where rows are permissions and columns are the actions declared for that permission. Cells contain check (✓) for granted, empty box (☐) for not granted, dash (—) when the action is not applicable to that permission
- Bottom meta strip: count summary, user count, creation date, last-edit timestamp, auto-save indicator

**Interaction model:**

- Clicking a checkbox toggles the grant immediately and saves via AJAX. There is no global save button.
- The auto-save indicator at the bottom right shows `✓ Saved` after each successful write.
- Failed saves show an inline error and revert the checkbox state.

### 10.3 Roles screen

A simple list of roles in a sortable table with columns: name, label, description, user count, created date, system flag. Buttons: Create new role (opens modal with name/label/description fields), Edit selected, Clone selected (creates a new role with all grants copied), Delete selected (blocked by `ON DELETE RESTRICT` if users are assigned).

### 10.4 Users screen

Search box at the top (autocomplete by EVO user fullname or login). Selected user's profile card shows:

- Current role (with link to switch)
- Effective permission map (what they can actually do, after override resolution)
- List of active overrides (grant or revoke), each with reason and creation timestamp
- Buttons to add a new override or remove an existing one

### 10.5 Audit log screen

A table bound to `AuditLogger::search()`. Filters: actor, target user, target role, action type, date range. Each row shows: timestamp, actor, action, target, old/new value, expandable details JSON.

### 10.6 UI implementation and override pattern

The package ships a **minimal reference UI** built on **Bootstrap 5 + vanilla JS**:

- **Bootstrap 5.3** (MIT) loaded from jsDelivr CDN — provides layout, navbar, table, alert, badge components
- **Vanilla JS `fetch()`** for AJAX — no jQuery, no framework
- **Shared `eaFetch()` helper** in `views/layout.blade.php` handles 401/403 responses with toast notifications
- **No build step** — all JS is inline in blade `<script>` tags

The reference UI is intentionally functional rather than polished:

- The matrix view in the package is **read-only** (loads permissions + grants but doesn't yet render checkboxes for editing)
- The roles/users/audit views render basic Bootstrap tables wired to the JSON endpoints
- Modal dialogs for create/edit/override are not included in the reference UI

The full visual model from §10.2 (sidebar role selector, accordion modules, inline checkboxes with auto-save, colour-coded counters, meta strip) is the **target experience** that consumer projects implement via the **view override pattern**:

1. Consumer runs `php artisan vendor:publish --tag=evo-access-views`
2. Laravel copies all 5 blade files into `resources/views/vendor/evoAccess/` (or the project's equivalent path)
3. Consumer rewrites the views using its own UI framework — Webix Pro, Tabler, AdminLTE, plain Bootstrap with htmx, etc. — keeping the same `eaFetch`-style endpoint contracts
4. Laravel's view resolver picks up the local override automatically; package upgrades don't overwrite the customised views

This separation keeps the package fully open-source (no GPL caascade from non-MIT UI libraries), allows consumer projects to use commercial frameworks they already license (e.g. Webix Pro), and lets each consumer match its existing admin look-and-feel without forking the package.

---

## 11. Bootstrap on first install

On a fresh install of the package:

1. `composer require saniock/evo-access`
2. `php artisan vendor:publish --tag=evo-access-config`
3. Edit the published `core/custom/config/access/bootstrap.php` to list the EVO user IDs that should become superadmin
4. `php artisan migrate` — applies the six migrations and seeds the `superadmin` role
5. `php artisan evoaccess:bootstrap` — assigns the configured user IDs to the superadmin role (via `BootstrapSuperadminSeeder`, idempotent)
6. The configured users can now log into the EVO manager and access `/access/matrix`

### 11.1 Bootstrap config

```php
// core/custom/config/access/bootstrap.php
return [
    'superadmin_user_ids' => [
        7,        // primary admin
        // <id>, // additional superadmins — uncomment when needed
    ],
];
```

### 11.2 Bootstrap command

`evoaccess:bootstrap` is idempotent and can be re-run safely. It only **inserts** missing assignments; it never overwrites or deletes existing ones. If a user listed in the config already has a different role, the command emits a warning and skips that user (the admin must change their role through the UI to leave an audit trail).

---

## 12. Migration from legacy systems

The package itself has nothing to migrate — it's new. This section describes how a consumer project (specifically Ddaudio) migrates from its existing access-control sprawl to EvoAccess. It is **not part of the package** but is documented here so consumer projects can follow the same path.

### 12.1 The legacy state in Ddaudio

Three systems coexist today:

- `assets/plugins/ddAudio/plugin.ManagerAccess.php` — EVO plugin that hardcodes `$roles[X]['modules']` and per-user `$userRoles[X] = [...]` exception lists
- `protected array $managerAccess = [1, 7, 66865]` — the same three EVO user IDs hardcoded in five module BaseControllers (NovaPoshta, Platform, Proxies, Finances, Logistics)
- `in_array($_SESSION['mgrRole'], $allowedRoles)` checks scattered across Orders, Finances, Analytics, Users module controllers

### 12.2 Strategy: parallel operation

The legacy system continues to run during migration. EvoAccess is installed alongside it. Each consumer module is migrated independently:

1. Install the package and bootstrap a superadmin (§11)
2. Register permissions for all consumer modules and run `evoaccess:sync-permissions`
3. Manually create DD roles in `/access/matrix` corresponding to the legacy EVO roles
4. Manually grant permissions to each role through the matrix
5. Use `evoaccess:migrate-legacy-evo-roles` (see §12.3) to assign existing managers to DD roles based on their EVO `user_attributes.role`
6. Manually import `$userRoles[...]` overrides from the legacy plugin file into the admin UI as user overrides (with `reason` set to "Imported from plugin.ManagerAccess.php — <name>")
7. Test on a non-critical user — log in, verify expected access
8. Begin migrating consumer module BaseControllers one at a time (§12.4)
9. After all modules are migrated, delete `plugin.ManagerAccess.php` and the hardcoded `managerAccess` arrays

During steps 1–8, the legacy system stays in effect. EvoAccess is "shadow mode" — its data exists but is not consulted by any consumer code.

### 12.3 Semi-automated user assignment

The package ships an `evoaccess:migrate-legacy-evo-roles` console command that takes a JSON mapping file (`evo_role_id → dd_role_slug`) and creates `ea_user_roles` rows for every EVO user with `user_attributes.role > 0`.

```bash
# Mapping file shape:
# { "5": "seo", "6": "shop_manager", "17": "analytics_viewer", ... }

php artisan evoaccess:migrate-legacy-evo-roles --mapping=mapping.json --dry-run
php artisan evoaccess:migrate-legacy-evo-roles --mapping=mapping.json
```

The command:

- Skips users who already have an `ea_user_roles` row (e.g. bootstrap superadmin)
- Skips users whose EVO role has no entry in the mapping (with a warning)
- Reports per-user actions in a clear log
- Supports `--dry-run` for safe inspection before commitment

This command is **only** for the initial migration. After it has run once, all subsequent role changes happen through the admin UI.

### 12.4 Migrating a single consumer module

The pattern for converting one Ddaudio module BaseController:

**Before:**

```php
class BaseController
{
    protected int $managerId;
    protected array $managerAccess = [1, 7, 66865];
    protected array $viewData;

    public function __construct()
    {
        $this->managerId = (int)evo()->getLoginUserID('mgr');
        $this->viewData = [
            'canEditable' => (int)in_array($this->managerId, $this->managerAccess),
            // ...
        ];
    }
}
```

**After:**

```php
use Saniock\EvoAccess\Services\AccessService;

abstract class BaseController
{
    protected int $managerId;
    protected array $viewData;

    public function __construct(
        protected readonly AccessService $access,
    ) {
        $this->managerId = (int)evo()->getLoginUserID('mgr');

        $permission = $this->modulePermission();

        $this->viewData = [
            'canEditable' => (int)$this->access->can($permission, 'update', $this->managerId),
            'canActions'  => $this->access->actionsFor($permission, $this->managerId),
            // ...
        ];
    }

    abstract protected function modulePermission(): string;
}
```

Each subclass declares its own permission:

```php
class BankAccountsController extends BaseController
{
    protected function modulePermission(): string
    {
        return 'finances.bank_accounts';
    }
}
```

The legacy `canEditable` view variable is preserved as an alias for `canActions['update']`, so blade templates that reference `@if ($canEditable)` continue to work without modification. New blade templates can opt into the richer payload:

```blade
@if ($canActions['export'] ?? false)
    <button id="exportBtn">Export to CSV</button>
@endif
```

### 12.5 The legacy `ModuleAccess` class

Ddaudio has its own `EvolutionCMS\Ddaudio\Services\ModuleAccess` class (created during an earlier module-access refactor). It is **kept** during and after the migration but rewritten as a thin facade that forwards to `Saniock\EvoAccess\Services\AccessService`:

```php
namespace EvolutionCMS\Ddaudio\Services;

use Saniock\EvoAccess\Services\AccessService;

class ModuleAccess
{
    public function __construct(
        private readonly AccessService $delegate,
    ) {}

    public function canView(array $menu, string $actionId, int $managerId): bool
    {
        return $this->delegate->canView($menu, $actionId, $managerId);
    }

    public function canEdit(array $menu, string $actionId, int $managerId): bool
    {
        return $this->delegate->canEdit($menu, $actionId, $managerId);
    }

    public function filterMenu(array $menu, int $managerId): array
    {
        return $this->delegate->filterMenu($menu, $managerId);
    }
}
```

This means existing call-sites that import `EvolutionCMS\Ddaudio\Services\ModuleAccess` continue to work — only the underlying behaviour changes. No grep-and-replace across the codebase is required.

---

## 13. Rollout plan

The migration is broken into nine atomic, mergeable commits. The first four go into the package repository; the rest into the consumer (Ddaudio) repository.

| # | Commit | Repo | Purpose |
|---|---|---|---|
| 1 | `feat(evo-access): scaffold composer package` | package | composer.json, service provider, contracts, model stubs, plugin file, README, LICENSE |
| 2 | `feat(evo-access): add 6 migrations + superadmin seed` | package | DDL of all six tables + initial superadmin row |
| 3 | `feat(evo-access): implement core services` | package | AccessService, PermissionCatalog, PermissionResolver, AuditLogger with full bodies |
| 4 | `feat(evo-access): admin matrix UI` | package | Controllers, blade views, routes for the four admin sections |
| 5 | `feat(ddaudio): consume evo-access via path repo` | Ddaudio | composer.json path repo, DdaudioServiceProvider registers permissions, bootstrap config |
| 6 | `feat(ddaudio): add permissions.php for all modules` | Ddaudio | One `config/permissions.php` file per consumer module |
| 7 | `refactor(ddaudio/<module>): migrate to evo-access` | Ddaudio | Replace `managerAccess` and `_SESSION['mgrRole']` for one module — repeated 8 times |
| 8 | `chore(ddaudio): remove plugin.ManagerAccess.php` | Ddaudio | Delete the legacy plugin file (final step) |
| 9 | `refactor(ddaudio): ModuleAccess → facade over EvoAccess` | Ddaudio | Thin adapter so old import paths keep working |

Between commits 5 and the first commit 7, the package is installed and operational but no consumer module yet uses it — both systems run in parallel. This is the right time to manually populate roles and overrides through the admin UI without risk.

Commit 7 is repeated once per module. Recommended order: start with the smallest module (`Warehouses` or `Platform`) to debug the conversion process, then proceed to the larger ones (`Orders`, `Finances`).

Commit 8 is the most consequential and should be performed last, only after a complete smoke test where one user from each role logs in and verifies expected access. Before commit 8, the legacy plugin can be locally renamed to `__plugin.ManagerAccess.php` (the project's local backup convention) for an additional safety period before deletion.

---

## 14. Future work (out of scope for v1)

The following are deliberately deferred:

- **Compare mode** in the matrix UI (view two roles side-by-side)
- **Role hierarchy / inheritance** (parent-role chains)
- **Multi-role per user** (composition instead of single-role)
- **Tabs-based role switcher** above the matrix (current design uses sidebar)
- **Redis cache layer** wrapping `PermissionResolver` for high-traffic installs
- **Pre-warm middleware** that loads the resolver for the current user on session start
- **Permission categories / tags** for filtering large catalogs
- **API tokens with scoped permissions** (currently the package targets manager-session access only)
- **Time-bounded overrides** (auto-expire after N days)
- **Bulk operations in the matrix** (apply same grant to multiple roles at once)

Adding any of these is non-breaking with the v1 API.

---

## Appendix A: Glossary

| Term | Meaning |
|---|---|
| **Permission** | A named capability declared by a module, with one or more actions. Identified by a `module.section[.subsection]` slug. |
| **Action** | A specific operation that can be performed on a permission (e.g. `view`, `update`, `export`). Each permission declares its own actions. |
| **Role** | A named bundle of grants. Users are assigned to exactly one role. |
| **Grant** | A row in `ea_role_permission_actions` saying "this role has this action on this permission". |
| **Override** | A row in `ea_user_overrides` that adds (`grant`) or removes (`revoke`) one specific action for one specific user. |
| **System role** | A role flagged with `is_system = 1`. Bypasses the matrix entirely. The package ships exactly one system role: `superadmin`. |
| **Catalog** | The in-memory registry of all known permissions, populated by consumer projects via `registerPermissions()`. |
| **Bootstrap** | The one-time process of assigning the configured EVO user IDs to the superadmin role on first install. |

## Appendix B: Decision log

| Decision | Choice | Alternative considered | Rationale |
|---|---|---|---|
| Scope | Full DB-backed system with admin UI + overrides + audit | Config-only or matrix-only | Long-term maintainability across multiple consumer projects |
| Action model | Per-permission custom action sets | Fixed enum (view/edit/admin) | Modules need custom actions like `refund`, `publish` |
| Role model | Flat with cloning | Hierarchy / multi-role | Simplest mental model; cloning covers the "almost like X" case |
| User model | One role per user + overrides | Multi-role union | Avoids "which role wins" ambiguity |
| Override model | Grant + revoke | Grant only | Real legacy data has both directions |
| Sidebar filtering | Hide entirely on no view | Show empty | Self-explanatory; matches existing pattern |
| Permission storage of actions | JSON column on `ea_permissions` | Separate `permission_actions` table | Read-mostly, always read together, no JOIN needed |
| `ea_user_roles` PK | `user_id` only | `(user_id, role_id)` composite | Enforces "one role per user" at schema level |
| `ea_user_roles.role_id` FK | `ON DELETE RESTRICT` | `CASCADE` | Prevents accidental loss of access for many users at once |
| Audit FKs | None | FKs on actor/target/permission | Audit must survive deletions |
| Resolver cache | Per-request in-memory | Redis-backed with TTL | Simpler v1; Redis can be added later as a decorator |
| Cache flush on grant change | `forgetAll()` | Targeted `forgetUser()` per affected user | Avoids extra SELECT; per-request cost is negligible |
| Catalog discovery | Explicit `registerPermissions()` calls | Auto-scan consumer module directories | Keeps the package agnostic to consumer file layout |
| Audit transactionality | Non-transactional with mutation | Wrapped in DB transaction | Crash window is vanishingly small; simplicity wins |
| Decoupling from EVO native role | Separate `ea_user_roles` table | Reuse `user_attributes.role` | EVO role stays as login gate only; no schema interference |
| Legacy `ModuleAccess` | Keep as facade adapter | Delete and update all imports | Zero call-site changes during migration |
