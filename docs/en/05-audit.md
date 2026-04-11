# Audit page

Log of all changes in the access system. Every change — creating/editing/deleting a role, assigning/removing a role from a manager, adding/removing an override — is recorded in the log.

## Why it's needed

1. **Security** — you can see who and when changed whose rights. If a manager got access they shouldn't have — the log shows who gave it to them.
2. **Debugging** — if a manager complains that "yesterday it worked, today it doesn't" — the log shows what changed.
3. **Reporting** — full history of actions with rights.

## Log columns

- **Date/Time** — when it happened
- **Actor** — who made the change (manager ID)
- **Action** — type of action:
  - `grant` — permission added to a role
  - `revoke` — permission removed from a role
  - `user_assigned` — manager assigned to a role
  - `user_role_changed` — manager's role changed
  - `role_created`, `role_deleted`, `role_cloned` — role CRUD
  - `override_grant`, `override_revoke`, `override_removed` — override changes
- **Role ID** — role ID (if the action is about a role)
- **User ID** — manager ID (if the action is about a manager)
- **Perm ID** — permission ID
- **Old / New** — old and new values (for actions where this makes sense)
- **Details** — additional data in JSON format

## Filters

The filter panel lets you narrow the log:

- **From / To** — date range
- **Actor ID** — only actions of a specific manager
- **Action type** — only one type of action

Click **Filter** to apply. Without filters the last 500 entries are shown.

## Cannot be edited

The log is **read-only**. It cannot be cleared through the UI — this is intentional so no one can cover their tracks.
