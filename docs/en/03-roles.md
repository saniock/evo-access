# Roles page

Managing roles — creating, editing, cloning, deleting.

## What a role is

A role is a **named permission template**. Instead of manually configuring rights for each of 40 managers, you create several roles (e.g. `seller`, `warehouse`, `marketing`) and assign them to groups.

When you **change a role's permissions** (on the *Matrix* page), changes are automatically applied to **all managers** with that role. This is the main benefit — bulk management.

## Creating a role

Click **Create Role**. In the popup fill in:

- **Name (slug)** — system name of the role, Latin letters, digits, underscores only. Example: `warehouse_manager`. **Cannot be changed** after creation.
- **Label** — display name, can be in your language, used in interfaces
- **Description** — description of the role's purpose (optional)

After creation the role appears in the list. To add permissions, go to the **Matrix** page.

## Cloning a role

If you need a role similar to an existing one, with minor differences — don't create from scratch. Click the **copy** icon next to the existing role. In the popup enter a new name — the system creates a new role with **all permissions** of the original.

You can then modify the clone's permissions without touching the original.

## Editing

The **pencil** icon lets you change `label` and `description`. The `name` (slug) field cannot be changed — it's used in code.

## Deletion

The **trash** icon deletes the role.

- **Role without assigned managers** — deleted immediately
- **Role with assigned managers** — a popup appears offering to reassign them to another role. Select a new role — all managers from the current role get the new one, and the old one is deleted. The operation is atomic: if something goes wrong, nothing changes.

## System roles

The `superadmin` role is marked as **system** and has special behavior: it **bypasses the matrix** and allows everything. It cannot be edited, cloned, or deleted through the UI. Use it only for main admin accounts.
