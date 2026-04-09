@extends('evoAccess::layout')

@section('title', 'Roles')

@section('content')
    <div class="row mt-3">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <div>
                    <h3 class="mb-0">Roles</h3>
                    <p class="text-muted small mb-0">Create, rename, clone, or delete access-control roles. System roles cannot be modified.</p>
                </div>
                <button type="button" class="btn btn-primary" id="ea-btn-create-role">+ New Role</button>
            </div>

            <table class="table table-striped table-hover align-middle">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Label</th>
                        <th>Description</th>
                        <th class="text-end">Users</th>
                        <th class="text-center">System</th>
                        <th class="text-end" style="width: 230px">Actions</th>
                    </tr>
                </thead>
                <tbody id="ea-roles-body">
                    <tr><td colspan="6" class="text-center text-muted">Loading…</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    {{-- Create role modal --}}
    <div class="modal fade" id="ea-modal-create" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="ea-form-create">
                    <div class="modal-header">
                        <h5 class="modal-title">Create new role</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Slug <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="name" required pattern="^[a-z][a-z0-9_]*$" maxlength="64">
                            <div class="form-text">Lowercase, digits and underscores only. Starts with a letter. Immutable after creation.</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Label <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="label" required maxlength="128">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="2" maxlength="255"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Edit role modal --}}
    <div class="modal fade" id="ea-modal-edit" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="ea-form-edit">
                    <input type="hidden" name="id">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit role</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Slug</label>
                            <input type="text" class="form-control" name="name_display" disabled>
                            <div class="form-text">Slug is immutable.</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Label <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="label" required maxlength="128">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="2" maxlength="255"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@section('script')
<script>
let eaCreateModal = null;
let eaEditModal = null;

async function loadRoles() {
    try {
        const roles = await eaFetch('/roles/data');
        const tbody = document.getElementById('ea-roles-body');
        tbody.innerHTML = '';

        if (!roles.length) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">No roles yet</td></tr>';
            return;
        }

        for (const role of roles) {
            const tr = document.createElement('tr');
            tr.dataset.roleId = role.id;
            tr.dataset.role = JSON.stringify({
                id: role.id,
                name: role.name,
                label: role.label,
                description: role.description,
                is_system: role.is_system,
            });

            const editBtn = role.is_system
                ? '<button class="btn btn-sm btn-outline-secondary" disabled title="System role">Edit</button>'
                : '<button class="btn btn-sm btn-outline-primary ea-btn-edit">Edit</button>';

            const deleteBtn = role.is_system
                ? '<button class="btn btn-sm btn-outline-secondary" disabled title="System role">Delete</button>'
                : '<button class="btn btn-sm btn-outline-danger ea-btn-delete">Delete</button>';

            tr.innerHTML = `
                <td><code>${escapeHtml(role.name)}</code></td>
                <td>${escapeHtml(role.label || '')}</td>
                <td class="text-muted">${escapeHtml(role.description || '')}</td>
                <td class="text-end">${role.user_assignments_count ?? 0}</td>
                <td class="text-center">${role.is_system ? '<span class="badge bg-warning">system</span>' : ''}</td>
                <td class="text-end">
                    <div class="btn-group btn-group-sm" role="group">
                        ${editBtn}
                        <button class="btn btn-sm btn-outline-secondary ea-btn-clone">Clone</button>
                        ${deleteBtn}
                    </div>
                </td>
            `;
            tbody.appendChild(tr);
        }

        // Wire row buttons
        tbody.querySelectorAll('.ea-btn-edit').forEach(b => b.addEventListener('click', onEditClick));
        tbody.querySelectorAll('.ea-btn-clone').forEach(b => b.addEventListener('click', onCloneClick));
        tbody.querySelectorAll('.ea-btn-delete').forEach(b => b.addEventListener('click', onDeleteClick));
    } catch (e) {
        // error already shown by eaFetch
    }
}

function getRoleFromRow(btn) {
    const tr = btn.closest('tr');
    return JSON.parse(tr.dataset.role);
}

async function onCreateSubmit(e) {
    e.preventDefault();
    const form = e.target;
    const data = {
        name: form.name.value.trim(),
        label: form.label.value.trim(),
        description: form.description.value.trim() || null,
    };

    try {
        await eaFetch('/roles', {
            method: 'POST',
            body: JSON.stringify(data),
        });
        eaCreateModal.hide();
        form.reset();
        eaToast('Role created');
        loadRoles();
    } catch (err) {
        // error already shown
    }
}

function onEditClick(e) {
    const role = getRoleFromRow(e.currentTarget);
    const form = document.getElementById('ea-form-edit');
    form.id.value = role.id;
    form.name_display.value = role.name;
    form.label.value = role.label || '';
    form.description.value = role.description || '';
    eaEditModal.show();
}

async function onEditSubmit(e) {
    e.preventDefault();
    const form = e.target;
    const id = form.id.value;
    const data = {
        label: form.label.value.trim(),
        description: form.description.value.trim() || null,
    };

    try {
        await eaFetch('/roles/' + id, {
            method: 'PUT',
            body: JSON.stringify(data),
        });
        eaEditModal.hide();
        eaToast('Role updated');
        loadRoles();
    } catch (err) {
        // error already shown
    }
}

async function onCloneClick(e) {
    const role = getRoleFromRow(e.currentTarget);
    if (!confirm('Clone role "' + (role.label || role.name) + '" with all its grants?')) return;

    try {
        await eaFetch('/roles/' + role.id + '/clone', { method: 'POST' });
        eaToast('Role cloned');
        loadRoles();
    } catch (err) {
        // error already shown
    }
}

async function onDeleteClick(e) {
    const role = getRoleFromRow(e.currentTarget);
    if (!confirm('Delete role "' + (role.label || role.name) + '"?\n\nThis cannot be undone. The role can only be deleted if it has no users assigned.')) return;

    try {
        await eaFetch('/roles/' + role.id, { method: 'DELETE' });
        eaToast('Role deleted');
        loadRoles();
    } catch (err) {
        // error already shown
    }
}

function escapeHtml(str) {
    if (str == null) return '';
    return String(str).replace(/[&<>"']/g, c => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
    }[c]));
}

// Script tag lives at end of body via @yield('script') — DOM is
// fully parsed by the time this runs, so no DOMContentLoaded needed.
eaCreateModal = new bootstrap.Modal(document.getElementById('ea-modal-create'));
eaEditModal = new bootstrap.Modal(document.getElementById('ea-modal-edit'));

document.getElementById('ea-btn-create-role').addEventListener('click', () => {
    document.getElementById('ea-form-create').reset();
    eaCreateModal.show();
});

document.getElementById('ea-form-create').addEventListener('submit', onCreateSubmit);
document.getElementById('ea-form-edit').addEventListener('submit', onEditSubmit);

loadRoles();
</script>
@endsection
