# Manual Smoke Test Checklist

This procedure verifies the saniock/evo-access package works end-to-end in a real Evolution CMS 3 install. Run after every major change before tagging a release.

## Prerequisites

- A Ddaudio (or other EVO 3.5+) project with `core/custom/composer.json`
- The evo-access package available locally at `core/custom/packages/evo-access/`
- A test EVO user account with `user_attributes.role > 0` (so they can log into the manager)

## Step 1: Install the package via path repo

In `core/custom/composer.json` of the consumer project:

```json
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

Then install:

```bash
cd core/custom
composer require saniock/evo-access:@dev
```

**Verify:** `vendor/saniock/evo-access/` exists as a symlink pointing at `packages/evo-access/`.

## Step 2: Publish config

```bash
php artisan vendor:publish --tag=evo-access-config
```

**Verify:** `core/custom/config/evoAccess.php` exists.

## Step 3: Run migrations

```bash
php artisan migrate
```

**Verify:** 6 new tables exist:
- `ea_roles` (with one row: superadmin)
- `ea_permissions` (initially empty)
- `ea_role_permission_actions` (empty)
- `ea_user_roles` (empty)
- `ea_user_overrides` (empty)
- `ea_audit_log` (empty)

```bash
mysql -e "SHOW TABLES LIKE 'ea_%'"
```

## Step 4: Configure bootstrap superadmin

Edit `core/custom/config/evoAccess.php`:

```php
return [
    'bootstrap_superadmin_user_ids' => [
        7,  // your EVO user_id
    ],
    // ... rest stays default
];
```

## Step 5: Run bootstrap

```bash
php artisan evoaccess:bootstrap
```

**Expected output:** `Bootstrap complete: 1 new, 0 already existed.`

**Verify:**

```bash
mysql -e "SELECT user_id, role_id FROM ea_user_roles"
```

Should show one row mapping your user_id to the superadmin role's id.

## Step 6: Sync the package's own permissions

```bash
php artisan evoaccess:sync-permissions
```

**Expected output:** `Sync complete: created 1, updated 0, orphaned 0` — the `access.admin` permission registered by the package itself.

## Step 7: Open the admin UI

Navigate to:

```
https://your-ddaudio-domain.tld/access/matrix
```

(While logged into the EVO manager as the user from Step 4.)

**Verify:**
- Page loads (no 401, no 500)
- Webix UI renders
- `superadmin` role appears in the left-side list
- Clicking `superadmin` shows the `access.admin` permission row

## Step 8: Test the audit log

In a separate terminal:

```bash
mysql -e "SELECT id, action, target_role_id, created_at FROM ea_audit_log ORDER BY id DESC LIMIT 10"
```

When you do anything via the admin UI (create role, assign user, etc.), this table should grow.

## Step 9: Verify EVO menu integration

In the EVO manager top menu, look for an "Access" entry under "Tools" (or whichever category is configured in `evoAccess.manager_menu.category`). Clicking it should land at `/access/matrix`.

## Pass criteria

All 9 steps complete without errors. If any step fails, escalate to the package developer.

## What this smoke test does NOT cover

- Performance under load (would need a load-test setup)
- Migration from legacy `plugin.ManagerAccess.php` (covered by separate Phase B plan)
- Multi-user concurrent edits (rare in a small-team admin UI)
- Internationalization beyond the seeded `Суперадмін` label
