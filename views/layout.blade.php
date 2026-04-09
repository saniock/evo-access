<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>EvoAccess — @yield('title', 'Access')</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { padding-top: 70px; }
        .ea-sidebar { background: #f8f9fa; border-right: 1px solid #dee2e6; min-height: calc(100vh - 70px); }
        .ea-sidebar .list-group-item { cursor: pointer; border-radius: 0; }
        .ea-sidebar .list-group-item.active { background: #0d6efd; border-color: #0d6efd; }
        .ea-matrix-counter { font-size: .75rem; background: #e9ecef; padding: .1rem .5rem; border-radius: .75rem; }
        .ea-module-section { margin-bottom: 1.5rem; }
        .ea-module-header { background: #f1f3f5; padding: .5rem 1rem; font-weight: 600; border-radius: .25rem .25rem 0 0; border: 1px solid #dee2e6; }
        .ea-module-table { border: 1px solid #dee2e6; border-top: none; border-radius: 0 0 .25rem .25rem; margin-bottom: 0; }
        .ea-saving { position: fixed; top: 80px; right: 1rem; z-index: 1050; }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-md navbar-dark bg-dark fixed-top">
    <div class="container-fluid">
        <span class="navbar-brand">
            <strong>EvoAccess</strong>
        </span>
        <ul class="navbar-nav">
            <li class="nav-item"><a class="nav-link" href="{{ url('access/roles') }}">Roles</a></li>
            <li class="nav-item"><a class="nav-link" href="{{ url('access/matrix') }}">Matrix</a></li>
            <li class="nav-item"><a class="nav-link" href="{{ url('access/users') }}">Users</a></li>
            <li class="nav-item"><a class="nav-link" href="{{ url('access/audit') }}">Audit</a></li>
        </ul>
    </div>
</nav>

<main class="container-fluid">
    @yield('content')
</main>

<div id="ea-toast" class="ea-saving"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const EVO_ACCESS_BASE = '{{ url('access') }}';

    /**
     * Fetch helper with JSON handling + auth error surfacing.
     */
    async function eaFetch(path, options = {}) {
        const url = EVO_ACCESS_BASE + path;
        const opts = Object.assign({
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        }, options);

        const response = await fetch(url, opts);

        if (response.status === 401) {
            eaToast('Not logged in — please log into the EVO manager', 'danger');
            throw new Error('401');
        }
        if (response.status === 403) {
            eaToast('Access denied — you need the access.admin permission', 'danger');
            throw new Error('403');
        }
        if (!response.ok) {
            const text = await response.text();
            eaToast('Request failed: ' + response.status + ' ' + text.slice(0, 200), 'danger');
            throw new Error(String(response.status));
        }

        return response.json();
    }

    /**
     * Transient toast notification.
     */
    function eaToast(message, variant = 'success') {
        const el = document.getElementById('ea-toast');
        el.innerHTML = `<div class="alert alert-${variant} alert-dismissible shadow-sm" role="alert">${message}<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>`;
        setTimeout(() => {
            const alert = el.querySelector('.alert');
            if (alert) bootstrap.Alert.getOrCreateInstance(alert).close();
        }, 4000);
    }

    /**
     * Read CSRF token from meta tag if present (EVO may not set one —
     * requests will work anyway via session cookies).
     */
    function eaCsrfHeader() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? { 'X-CSRF-TOKEN': meta.getAttribute('content') } : {};
    }
</script>

@yield('script')

</body>
</html>
