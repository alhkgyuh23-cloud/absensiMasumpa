<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Scan Kartu')</title>
    @include('layouts._favicon')
    @vite(['resources/css/app.css', 'resources/js/scan.js'])
</head>
<body class="min-h-screen bg-slate-900 text-white antialiased">
    @yield('content')
</body>
</html>