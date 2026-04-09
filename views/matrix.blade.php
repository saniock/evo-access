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
            <p id="ea-matrix-subtitle" class="text-muted small">Click a role on the left to view its permissions.</p>
            <div id="ea-matrix-content"></div>
        </div>
    </div>
@endsection

@section('script')
<script>
let eaCurrentRoleId = null;

async function loadRoles() {
    const roles = await eaFetch('/roles');
    const list = document.getElementById('ea-roles-list');
    list.innerHTML = '';

    for (const role of roles) {
        const item = document.createElement('button');
        item.type = 'button';
        item.className = 'list-group-item list-group-item-action d-flex justify-content-between align-items-center';
        item.dataset.roleId = role.id;
        item.innerHTML = `
            <span>${role.label || role.name}${role.is_system ? ' 🔒' : ''}</span>
            <span class="badge bg-secondary rounded-pill">${role.user_assignments_count ?? 0}</span>
        `;
        item.addEventListener('click', () => selectRole(role));
        list.appendChild(item);
    }
}

async function selectRole(role) {
    eaCurrentRoleId = role.id;

    // Highlight the selected row
    document.querySelectorAll('#ea-roles-list .list-group-item').forEach(el => {
        el.classList.toggle('active', parseInt(el.dataset.roleId, 10) === role.id);
    });

    document.getElementById('ea-matrix-title').textContent = role.label || role.name;
    document.getElementById('ea-matrix-subtitle').textContent = role.description || '';

    const content = document.getElementById('ea-matrix-content');
    content.innerHTML = '<div class="text-muted">Loading permissions…</div>';

    try {
        const data = await eaFetch('/matrix/data/' + role.id);
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
        const totalActions = perms.reduce((sum, p) => sum + (p.actions || []).length, 0);
        const grantedCount = perms.reduce((sum, p) => {
            const g = grants[p.id] || [];
            return sum + g.length;
        }, 0);

        const section = document.createElement('div');
        section.className = 'ea-module-section';
        section.innerHTML = `
            <div class="ea-module-header d-flex justify-content-between align-items-center">
                <span>${module}</span>
                <span class="ea-matrix-counter">${grantedCount} / ${totalActions}</span>
            </div>
            <table class="table table-sm ea-module-table mb-0">
                <thead>
                    <tr>
                        <th>Permission</th>
                        <th>Actions</th>
                        <th>Granted</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        `;
        const tbody = section.querySelector('tbody');

        for (const perm of perms) {
            const permGrants = (grants[perm.id] || []).map(g => g.action).sort();
            const allActions = (perm.actions || []).sort();
            const grantedSet = new Set(permGrants);

            const cells = allActions.map(action => {
                const has = grantedSet.has(action);
                return `<span class="badge ${has ? 'bg-success' : 'bg-light text-muted border'} me-1">${action}</span>`;
            }).join('');

            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td><code>${perm.name}</code><br><small class="text-muted">${perm.label || ''}</small></td>
                <td>${allActions.map(a => `<span class="badge bg-secondary me-1">${a}</span>`).join('')}</td>
                <td>${cells}</td>
            `;
            tbody.appendChild(tr);
        }

        content.appendChild(section);
    }
}

loadRoles().catch(() => {});
</script>
@endsection
