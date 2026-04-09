@extends('evoAccess::layout')

@section('title', 'Permission Matrix')

@section('content')
    <div class="row mt-3">
        <div class="col-md-3 ea-sidebar">
            <h5 class="p-3 mb-0 border-bottom">Roles</h5>
            <div id="ea-roles-list" class="list-group list-group-flush">
                <div class="list-group-item text-muted">Loading…</div>
            </div>
        </div>
        <div class="col-md-9 py-3">
            <h3 id="ea-matrix-title" class="mb-1">Select a role</h3>
            <p id="ea-matrix-subtitle" class="text-muted small">Click a role on the left to view its permissions. Click an action badge to grant or revoke it.</p>
            <div id="ea-matrix-content"></div>
        </div>
    </div>
@endsection

@section('script')
<script>
let eaCurrentRole = null;
let eaSavingCount = 0;

async function loadRoles() {
    const roles = await eaFetch('/roles/data');
    const list = document.getElementById('ea-roles-list');
    list.innerHTML = '';

    if (!roles.length) {
        list.innerHTML = '<div class="list-group-item text-muted">No roles yet</div>';
        return;
    }

    for (const role of roles) {
        const item = document.createElement('button');
        item.type = 'button';
        item.className = 'list-group-item list-group-item-action d-flex justify-content-between align-items-center';
        item.dataset.roleId = role.id;
        item.innerHTML = `
            <span>${escapeHtml(role.label || role.name)}${role.is_system ? ' 🔒' : ''}</span>
            <span class="badge bg-secondary rounded-pill">${role.user_assignments_count ?? 0}</span>
        `;
        item.addEventListener('click', () => selectRole(role));
        list.appendChild(item);
    }
}

async function selectRole(role) {
    // Highlight the selected row
    document.querySelectorAll('#ea-roles-list .list-group-item').forEach(el => {
        el.classList.toggle('active', parseInt(el.dataset.roleId, 10) === role.id);
    });

    document.getElementById('ea-matrix-title').textContent = role.label || role.name;
    document.getElementById('ea-matrix-subtitle').textContent = role.is_system
        ? 'System role — bypasses the matrix entirely. All permissions are implicitly granted.'
        : (role.description || 'Click an action badge to grant or revoke it.');

    const content = document.getElementById('ea-matrix-content');
    content.innerHTML = '<div class="text-muted">Loading permissions…</div>';

    try {
        const data = await eaFetch('/matrix/data/' + role.id);
        eaCurrentRole = data.role;
        renderMatrix(data);
    } catch (e) {
        // error already shown
    }
}

function renderMatrix(data) {
    const content = document.getElementById('ea-matrix-content');
    const permissions = data.permissions || [];
    const grants = data.grants || {};

    if (!permissions.length) {
        content.innerHTML = '<div class="alert alert-info">No permissions registered. Run <code>php artisan evoaccess:sync-permissions</code> after registering module permissions.</div>';
        return;
    }

    // Group permissions by module
    const byModule = {};
    for (const perm of permissions) {
        (byModule[perm.module] ??= []).push(perm);
    }

    content.innerHTML = '';
    for (const [module, perms] of Object.entries(byModule)) {
        const section = document.createElement('div');
        section.className = 'ea-module-section';
        section.dataset.module = module;
        section.innerHTML = `
            <div class="ea-module-header d-flex justify-content-between align-items-center">
                <span>${escapeHtml(module)}</span>
                <span class="ea-matrix-counter" data-module-counter="${module}">0 / 0</span>
            </div>
            <table class="table table-sm ea-module-table mb-0">
                <thead>
                    <tr>
                        <th style="width: 35%">Permission</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        `;
        const tbody = section.querySelector('tbody');

        for (const perm of perms) {
            const permGrants = (grants[perm.id] || []).map(g => g.action);
            const grantedSet = new Set(permGrants);
            const actions = (perm.actions || []);

            const tr = document.createElement('tr');
            tr.dataset.permissionId = perm.id;

            const badgesHtml = actions.map(action => {
                const has = grantedSet.has(action);
                return renderActionBadge(perm.id, action, has);
            }).join('');

            tr.innerHTML = `
                <td>
                    <code>${escapeHtml(perm.name)}</code>
                    <br><small class="text-muted">${escapeHtml(perm.label || '')}</small>
                </td>
                <td class="ea-action-cell">${badgesHtml}</td>
            `;
            tbody.appendChild(tr);
        }

        content.appendChild(section);
    }

    // Wire up click handlers
    content.querySelectorAll('[data-ea-action]').forEach(el => {
        el.addEventListener('click', onActionClick);
    });

    refreshAllCounters();
}

function renderActionBadge(permissionId, action, granted) {
    const cls = granted ? 'bg-success' : 'bg-light text-muted border';
    return `<button type="button"
        class="badge ${cls} me-1 ea-action-badge"
        data-ea-action
        data-permission-id="${permissionId}"
        data-action="${escapeAttr(action)}"
        data-granted="${granted ? '1' : '0'}"
        style="cursor: pointer; border: none;">${escapeHtml(action)}</button>`;
}

async function onActionClick(e) {
    const btn = e.currentTarget;

    if (!eaCurrentRole) {
        eaToast('Select a role first', 'warning');
        return;
    }

    if (eaCurrentRole.is_system) {
        eaToast('System role bypasses the matrix — cannot edit grants', 'warning');
        return;
    }

    const permissionId = parseInt(btn.dataset.permissionId, 10);
    const action = btn.dataset.action;
    const wasGranted = btn.dataset.granted === '1';

    btn.disabled = true;
    eaSavingCount++;
    showSaving();

    try {
        if (wasGranted) {
            await eaFetch('/matrix/revoke', {
                method: 'DELETE',
                body: JSON.stringify({
                    role_id: eaCurrentRole.id,
                    permission_id: permissionId,
                    action: action,
                }),
            });
        } else {
            await eaFetch('/matrix/grant', {
                method: 'POST',
                body: JSON.stringify({
                    role_id: eaCurrentRole.id,
                    permission_id: permissionId,
                    action: action,
                }),
            });
        }

        // Toggle local state
        const nowGranted = !wasGranted;
        btn.dataset.granted = nowGranted ? '1' : '0';
        btn.classList.remove('bg-success', 'bg-light', 'text-muted', 'border');
        if (nowGranted) {
            btn.classList.add('bg-success');
        } else {
            btn.classList.add('bg-light', 'text-muted', 'border');
        }
        refreshAllCounters();
    } catch (err) {
        // eaFetch already showed the error toast
    } finally {
        btn.disabled = false;
        eaSavingCount--;
        if (eaSavingCount === 0) hideSaving();
    }
}

function refreshAllCounters() {
    document.querySelectorAll('.ea-module-section').forEach(section => {
        const module = section.dataset.module;
        const total = section.querySelectorAll('[data-ea-action]').length;
        const granted = section.querySelectorAll('[data-ea-action][data-granted="1"]').length;
        const counter = section.querySelector(`[data-module-counter="${module}"]`);
        if (counter) counter.textContent = `${granted} / ${total}`;
    });
}

function showSaving() {
    const el = document.getElementById('ea-toast');
    if (!el.querySelector('.alert-saving')) {
        el.innerHTML = '<div class="alert alert-info alert-saving shadow-sm py-2 px-3" role="alert"><span class="spinner-border spinner-border-sm me-2"></span>Saving…</div>';
    }
}

function hideSaving() {
    const el = document.getElementById('ea-toast');
    const saving = el.querySelector('.alert-saving');
    if (saving) saving.remove();
}

function escapeHtml(str) {
    if (str == null) return '';
    return String(str).replace(/[&<>"']/g, c => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
    }[c]));
}

function escapeAttr(str) {
    return escapeHtml(str);
}

loadRoles().catch(() => {});
</script>
@endsection
