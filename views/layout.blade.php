<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>EvoAccess — @yield('title', 'Access')</title>
    <link rel="stylesheet" href="//cdn.webix.com/edge/webix.css">
    <script src="//cdn.webix.com/edge/webix.js"></script>
    <style>
        html, body { height: 100%; margin: 0; }
        .ea-sidebar { background: #f8fafc; }
        .ea-active { background: #3b82f6 !important; color: #fff !important; }
    </style>
</head>
<body>
<div id="ea-app"></div>
<script>
    const EVO_ACCESS_BASE = '{{ url('access') }}';
    @yield('script')
</script>
</body>
</html>
