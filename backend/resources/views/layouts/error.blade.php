<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-gray-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? 'Error' }} - Spacepad</title>

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('components.scripts.faro')
</head>
<body class="h-full">
    <div class="flex min-h-full flex-col justify-center py-12 sm:px-6 lg:px-8">
        <div class="sm:mx-auto sm:w-full sm:max-w-md">
            <div class="flex items-center justify-center">
                <img class="h-9 w-9 me-2.5" src="/images/spacepad-logo.svg" alt="">
                <span class="text-lg leading-none font-bold tracking-tight text-oxford">Spacepad</span>
            </div>
            @yield('content')
        </div>
    </div>
</body>
</html> 