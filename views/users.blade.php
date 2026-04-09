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
            btn.innerHTML = `<strong>${u.fullname || '(unnamed)'}</strong><br><small class="text-muted">id ${u.user_id}</small>`;
            btn.addEventListener('click', () => loadUserDetail(u));
            results.appendChild(btn);
        }
    } catch (e) {}
}

async function loadUserDetail(user) {
    const detail = document.getElementById('ea-user-detail');
    detail.innerHTML = '<div class="text-muted">Loading…</div>';
    try {
        const data = await eaFetch('/users/' + user.user_id + '/effective');
        renderUserDetail(user, data);
    } catch (e) {}
}

function renderUserDetail(user, data) {
    const detail = document.getElementById('ea-user-detail');
    const roleId = data.role_id;
    const effective = data.effective || {};
    const overrides = data.overrides || [];

    const isSuperadmin = effective.__is_system === true;

    const permRows = isSuperadmin
        ? '<tr><td colspan="2" class="text-center"><span class="badge bg-warning">superadmin — all permissions granted</span></td></tr>'
        : Object.entries(effective).map(([name, actions]) => {
            const actionBadges = Object.entries(actions || {})
                .filter(([, v]) => v === true)
                .map(([a]) => `<span class="badge bg-success me-1">${a}</span>`)
                .join('') || '<span class="text-muted small">none</span>';
            return `<tr><td><code>${name}</code></td><td>${actionBadges}</td></tr>`;
        }).join('') || '<tr><td colspan="2" class="text-center text-muted">No effective permissions</td></tr>';

    const overrideRows = overrides.length
        ? overrides.map(o => `
            <tr>
                <td><code>${o.permission_id}</code></td>
                <td><code>${o.action}</code></td>
                <td><span class="badge bg-${o.mode === 'grant' ? 'success' : 'danger'}">${o.mode}</span></td>
                <td class="small text-muted">${o.reason || ''}</td>
            </tr>
        `).join('')
        : '<tr><td colspan="4" class="text-center text-muted">No overrides</td></tr>';

    detail.innerHTML = `
        <h4>${user.fullname || '(unnamed)'}</h4>
        <p class="text-muted">user_id: <code>${user.user_id}</code> · role_id: <code>${roleId ?? 'none'}</code></p>
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
}
</script>
@endsection
