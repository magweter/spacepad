<!doctype html>
<html class="h-full bg-white" lang="{{ App::currentLocale() }}">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1"/>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="htmx-config" content='{"selfRequestsOnly": false}' />

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

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/htmx.org@2.0.3"></script>

    {!! RecaptchaV3::initJs() !!}

    <!-- FontAwesome CDN for Google Icon -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">

    @stack('styles')
    <style>
        .bg-oxford {
            background: #14213D;
        }
        .bg-orange {
            background: #FCA311;
        }
        .text-orange {
            color: #FCA311;
        }
        .bg-platinum {
            background: #E5E5E5;
        }
        .grecaptcha-badge { visibility: hidden !important; }
    </style>
    @lemonJS
    @includeWhen(config('services.clarity.tag_code'), 'components.scripts.clarity')
</head>
<body class="h-full @yield('body-classes')">
@stack('modals')
<div class="min-h-full">
    @yield('page')
</div>
@stack('scripts')
</body>
</html>
