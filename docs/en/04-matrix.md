# Matrix page

Managing **role** permissions. Here you select a role and set up its rights to all site modules.

## Interface

On the left — **list of all roles** with the number of assigned managers in parentheses. On the right — **permissions table** for the selected role.

Click a role in the left list — the full table of all system permissions appears on the right, grouped by module. Each row is one permission (e.g. `orders.orders`). Columns are actions (view, edit, delete, import, etc.).

## Managing permissions

Click a **badge** in a cell to toggle the permission:

- **Gray —** click → **Green ✓** (add permission)
- **Green ✓** click → **Gray —** (remove permission)

Changes are **saved automatically** — no need to click Save. Each click is one request to the server that updates the database immediately.

## What adding a permission means

If you add a permission to the `seller` role for `orders.orders.view`, then **all managers** with that role immediately get the right to view orders. Next time a manager refreshes the page — they will see the *Orders* section.

## What removing a permission means

If you remove a permission, all managers with this role **lose access** to the section. Exceptions — managers who have an **override-grant** for the same action (they keep access personally).

## System roles

If you selected a system role (e.g. `superadmin`) — the table will be read-only. A system role always has **full access** and it cannot be changed through the matrix. This is done for safety — so you don't accidentally lock yourself out.

## When to use Matrix vs Users

- **Matrix** — when you change rights that should apply to **all** managers with a role. For example, you add a new module and want all sellers to see it.
- **Users** — when you need to grant/revoke something to one specific manager. In this case their role is not changed — you add an override on top of it.
