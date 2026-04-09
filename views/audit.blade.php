@extends('evoAccess::layout')

@section('title', 'Audit Log')

@section('content')
    <div class="row mt-3">
        <div class="col-12">
            <h3>Audit Log</h3>
            <p class="text-muted small">Chronological record of every access-control change.</p>
            <table class="table table-sm table-striped">
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>Actor</th>
                        <th>Action</th>
                        <th>Role</th>
                        <th>User</th>
                        <th>Perm</th>
                        <th>Old → New</th>
                    </tr>
                </thead>
                <tbody id="ea-audit-body">
                    <tr><td colspan="7" class="text-center text-muted">Loading…</td></tr>
                </tbody>
            </table>
        </div>
    </div>
@endsection

@section('script')
<script>
(async function () {
    try {
        const entries = await eaFetch('/audit/data?limit=100');
        const tbody = document.getElementById('ea-audit-body');
        tbody.innerHTML = '';

        if (!entries.length) {
            tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">No audit entries yet</td></tr>';
            return;
        }

        for (const e of entries) {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td class="text-nowrap small">${e.created_at ?? ''}</td>
                <td>${e.actor_user_id ?? ''}</td>
                <td><code>${e.action ?? ''}</code></td>
                <td>${e.target_role_id ?? ''}</td>
                <td>${e.target_user_id ?? ''}</td>
                <td>${e.permission_id ?? ''}</td>
                <td class="small">${e.old_value ?? '—'} → ${e.new_value ?? '—'}</td>
            `;
            tbody.appendChild(tr);
        }
    } catch (e) {}
})();
</script>
@endsection
