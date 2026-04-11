# Users page

This is the main page for managing rights of specific managers. You see all site managers here and can assign roles and targeted rights to them.

## Managers table

Each row is one manager. Columns:

- **ID** — manager's identifier in the system
- **Manager** — name from the EVO profile
- **Role** — current role (or empty if not assigned yet)
- **Modules** — modules the manager has any access to
- **Grants** — progress bar: how many actions are available out of total possible
- **Overrides** — count of targeted grants/revokes on top of the role
- **Edit** — button to open the full permission matrix

## Opening a manager's matrix

Click the **Edit** button or double-click the row. A popup opens with the manager's full permission matrix, grouped by module.

### Changing the role

At the top of the popup there's a **Role** dropdown. Select a new role — it will be applied after clicking **Save**.

### Permission badges

Each matrix cell is a **badge** of one of four colors:

- **Green ✓** — action allowed **from the role** (inherited)
- **Blue ✓** — action allowed **via override** (added personally to this manager)
- **Red ✗** — action **denied via override** (removed personally even though the role allows it)
- **Gray —** — no access

### How to change rights

Click badges to toggle states:

- **Gray → Blue** — add individual permission
- **Green → Red** — remove a right granted by the role
- **Blue → Gray** — remove the added individual permission
- **Red → Green** — return the role's permission

After all changes click **Save**. All overrides are saved in one batch.

## Module accordion

In the matrix, modules are auto-expanded if the manager has at least one grant in them. Modules without access are collapsed to save space. Click a module header to expand/collapse.

## Assigning a role to a new manager

If a manager doesn't have a role yet, they are shown with an empty **Role** field. Open their matrix, select a role from the list, click **Save**. The role is assigned — the manager gets all rights of that role.
