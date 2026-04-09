@extends('evoAccess::layout')

@section('title', 'Users')

@section('content')
    <div class="row mt-3">
        <div class="col-md-4">
            <h3>Users</h3>
            <p class="text-muted small">Search EVO managers by name or id.</p>
            <input type="text" id="ea-user-search" class="form-control" placeholder="Type to search…">
            <div id="ea-user-results" class="list-group mt-2"></div>
        </div>
        <div class="col-md-8">
            <div id="ea-user-detail" class="text-muted">Select a user to view their effective permissions.</div>
        </div>
    </div>
@endsection

@section('script')
    <script>
        let eaSearchTimer = null;
        let eaRolesCache = null;
        let eaSelectedUser = null;

        document.getElementById('ea-user-search').addEventListener('input', function (e) {
            clearTimeout(eaSearchTimer);
            const q = e.target.value.trim();
            eaSearchTimer = setTimeout(() => runSearch(q), 300);
        });

        async function runSearch(q) {
            const results = document.getElementById('ea-user-results');
            if (q.length < 2) {
                results.innerHTML = '';
                return;
            }
            try {
                const users = await eaFetch('/users/search?q=' + encodeURIComponent(q));
                results.innerHTML = '';
                if (!users.length) {
                    results.innerHTML = '<div class="list-group-item text-muted">No matches</div>';
                    return;
                }
                for (const u of users) {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'list-group-item list-group-item-action';
                    btn.innerHTML = `<strong>${escapeHtml(u.fullname || '(unnamed)')}</strong><br><small class="text-muted">id ${u.user_id}</small>`;
                    btn.addEventListener('click', () => loadUserDetail(u));
                    results.appendChild(btn);
                }
            } catch (e) {
            }
        }

        async function loadUserDetail(user) {
            eaSelectedUser = user;
            const detail = document.getElementById('ea-user-detail');
            detail.innerHTML = '<div class="text-muted">Loading…</div>';
            try {
                // Fetch roles list (cached) + user effective permissions in parallel
                const [roles, data] = await Promise.all([
                    eaRolesCache ? Promise.resolve(eaRolesCache) : eaFetch('/roles/data'),
                    eaFetch('/users/' + user.user_id + '/effective'),
                ]);
                eaRolesCache = roles;
                renderUserDetail(user, data, roles);
            } catch (e) {
            }
        }

        function renderUserDetail(user, data, roles) {
            const detail = document.getElementById('ea-user-detail');
            // Coerce to number defensively — PDO sometimes returns BIGINT
            // columns as strings, and `r.id === currentRoleId` would silently
            // fail to match `1` vs `"1"`. null/undefined → NaN, which never
            // matches a real id, so the "no role" option stays selected.
            const currentRoleId = data.role_id != null ? Number(data.role_id) : null;
            const effective = data.effective || {};
            const overrides = data.overrides || [];

            const isSuperadmin = effective.__is_system === true;

            // Build role dropdown options
            const roleOptions = ['<option value="">— no role —</option>']
                .concat(roles.map(r => {
                    const selected = currentRoleId !== null && Number(r.id) === currentRoleId ? ' selected' : '';
                    const sysFlag = r.is_system ? ' 🔒' : '';
                    return `<option value="${r.id}"${selected}>${escapeHtml(r.label || r.name)}${sysFlag}</option>`;
                }))
                .join('');

            const permRows = isSuperadmin
                ? '<tr><td colspan="2" class="text-center"><span class="badge bg-warning">superadmin — all permissions granted</span></td></tr>'
                : Object.entries(effective).map(([name, actions]) => {
                const actionBadges = Object.entries(actions || {})
                    .filter(([, v]) => v === true)
                    .map(([a]) => `<span class="badge bg-success me-1">${escapeHtml(a)}</span>`)
                    .join('') || '<span class="text-muted small">none</span>';
                return `<tr><td><code>${escapeHtml(name)}</code></td><td>${actionBadges}</td></tr>`;
            }).join('') || '<tr><td colspan="2" class="text-center text-muted">No effective permissions</td></tr>';

            const overrideRows = overrides.length
                ? overrides.map(o => `
            <tr>
                <td><code>${o.permission_id}</code></td>
                <td><code>${escapeHtml(o.action)}</code></td>
                <td><span class="badge bg-${o.mode === 'grant' ? 'success' : 'danger'}">${o.mode}</span></td>
                <td class="small text-muted">${escapeHtml(o.reason || '')}</td>
            </tr>
        `).join('')
                : '<tr><td colspan="4" class="text-center text-muted">No overrides</td></tr>';

            detail.innerHTML = `
        <h4>${escapeHtml(user.fullname || '(unnamed)')}</h4>
        <p class="text-muted">user_id: <code>${user.user_id}</code></p>

        <div class="card mb-4">
            <div class="card-body">
                <h6 class="card-subtitle text-muted mb-2">Role assignment</h6>
                <div class="input-group">
                    <select id="ea-role-select" class="form-select">${roleOptions}</select>
                    <button type="button" id="ea-btn-assign-role" class="btn btn-primary">Assign</button>
                </div>
                <div class="form-text">Each user can have at most one role. Saving here overwrites any existing assignment.</div>
            </div>
        </div>

        <h5 class="mt-4">Effective permissions</h5>
        <table class="table table-sm">
            <thead><tr><th>Permission</th><th>Actions</th></tr></thead>
            <tbody>${permRows}</tbody>
        </table>
        <h5 class="mt-4">User overrides</h5>
        <table class="table table-sm">
            <thead><tr><th>Permission</th><th>Action</th><th>Mode</th><th>Reason</th></tr></thead>
            <tbody>${overrideRows}</tbody>
        </table>
    `;

            document.getElementById('ea-btn-assign-role').addEventListener('click', onAssignRole);
        }

        async function onAssignRole() {
            if (!eaSelectedUser) return;

            const select = document.getElementById('ea-role-select');
            const roleId = select.value;

            if (!roleId) {
                eaToast('Pick a role first (or use a dedicated unassign action — not yet implemented)', 'warning');
                return;
            }

            const btn = document.getElementById('ea-btn-assign-role');
            btn.disabled = true;
            btn.textContent = 'Saving…';

            try {
                await eaFetch('/users/' + eaSelectedUser.user_id + '/assign', {
                    method: 'POST',
                    body: JSON.stringify({role_id: parseInt(roleId, 10)}),
                });
                eaToast('Role assigned');
                // Reload detail to refresh effective permissions
                loadUserDetail(eaSelectedUser);
            } catch (err) {
                // error already shown
                btn.disabled = false;
                btn.textContent = 'Assign';
            }
        }

        function escapeHtml(str) {
            if (str == null) return '';
            return String(str).replace(/[&<>"']/g, c => ({
                '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
            }[c]));
        }
    </script>
@endsection
