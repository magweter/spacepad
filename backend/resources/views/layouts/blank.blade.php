<!doctype html>
<html class="h-full bg-white" lang="{{ App::currentLocale() }}">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1"/>
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
    <link rel="manifest" href="/site.webmanifest">
    <link rel="mask-icon" href="/safari-pinned-tab.svg" color="#4fad32">
    <link rel="shortcut icon" href="/favicon.ico">
    <meta name="msapplication-TileColor" content="#ffffff">
    <meta name="msapplication-config" content="/browserconfig.xml">
    <meta name="theme-color" content="#ffffff">

    <meta name="robots" content="noindex, nofollow">
    <title>{{ config('app.name') }}</title>

    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/htmx.org@2.0.3"></script>

    <!-- FontAwesome CDN for Google Icon -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Urbanist:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">

    @stack('styles')
    <style>
        * {
            font-family: "Urbanist", sans-serif;
            font-optical-sizing: auto;
        }
        .bg-oxford {
            background: #14213D;
        }
        .bg-orange {
            background: #FCA311;
        }
        .bg-platinum {
            background: #E5E5E5;
        }
    </style>
</head>
<body class="h-full @yield('body-classes')">
@stack('modals')
<div class="min-h-full">
    @yield('page')
</div>
@stack('scripts')
<script>
    setTimeout(() => {
        const alert = document.getElementById('alert');
        if (alert) {
            alert.style.opacity = '0'; // Start fading out
            setTimeout(() => alert.remove(), 600); // Remove from DOM after fade out
        }
    }, 5000);
</script>
</body>
</html>
