# System overview

The **Access** module manages manager permissions for all admin sections of the site. Instead of a hardcoded list of "who has access to what", the system uses **roles** and **individual permissions**.

## Core concepts

**Permission** — a single entity in the system, e.g. `orders.orders` or `competitors.dracar.products`. Each permission has a set of actions: `view`, `edit`, `delete`, `import`, etc.

**Role** — a named set of permissions. For example, the `seller` role may have `view` and `edit` on orders but no access to warehouse.

**User** — a site manager. Each user can have one role (or none).

**Override** — a targeted exception to role rules. For example, a manager with the `seller` role can additionally receive access to one specific section that isn't in the role.

## How it works together

1. Admin creates **roles** on the *Roles* page
2. On the *Matrix* page admin sets up role permissions — which actions are allowed
3. On the *Users* page admin assigns roles to managers
4. For exceptional cases — adds **overrides** to specific managers

When a manager tries to open a section, the system checks:

1. Is the user a superadmin? → Yes → access granted
2. Is there an override to revoke (revoke)? → Yes → access denied
3. Is there an override to grant (grant)? → Yes → access granted
4. Does the role allow it? → Yes → access granted
5. Otherwise → access denied

## When to use override

**Role** is the **base** set of rights for a group of managers. If one manager needs temporary access to something — don't create a separate role, add an override to them instead.

Example: there's a `seller` role for 15 managers. You need Ivan Petrov to see a finance report that isn't in `seller`. Instead of creating a new role — open him in Users, click the `view` badge on `finances.reports` — a blue override-grant is added. The other 14 managers keep their rights unchanged.
