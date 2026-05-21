<!doctype html>
<html class="h-full" lang="{{ App::currentLocale() }}">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1"/>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name'))</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>
<body class="h-full overflow-hidden @yield('body-classes')">
    @yield('content')
    @stack('scripts')
</body>
</html>
