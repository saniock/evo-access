@extends('evoAccess::layout')

@section('title', 'Roles')

@section('content')
    <div class="row mt-3">
        <div class="col-12">
            <h3>Roles</h3>
            <p class="text-muted">List of all access-control roles. Create new roles, rename, delete, or clone existing ones.</p>
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Label</th>
                        <th>Description</th>
                        <th class="text-end">Users</th>
                        <th class="text-center">System</th>
                    </tr>
                </thead>
                <tbody id="ea-roles-body">
                    <tr><td colspan="5" class="text-center text-muted">Loading…</td></tr>
                </tbody>
            </table>
        </div>
    </div>
@endsection

@section('script')
<script>
(async function () {
    try {
        const roles = await eaFetch('/roles');
        const tbody = document.getElementById('ea-roles-body');
        tbody.innerHTML = '';

        if (!roles.length) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">No roles yet</td></tr>';
            return;
        }

        for (const role of roles) {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td><code>${role.name}</code></td>
                <td>${role.label || ''}</td>
                <td class="text-muted">${role.description || ''}</td>
                <td class="text-end">${role.user_assignments_count ?? 0}</td>
                <td class="text-center">${role.is_system ? '<span class="badge bg-warning">system</span>' : ''}</td>
            `;
            tbody.appendChild(tr);
        }
    } catch (e) {
        // error already shown by eaFetch
    }
})();
</script>
@endsection
