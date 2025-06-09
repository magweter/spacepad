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

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])

        {!! RecaptchaV3::initJs() !!}

        @stack('styles')
        @lemonJS
        @includeWhen(config('services.clarity.tag_code'), 'components.scripts.clarity')
    </head>
    <body class="h-full @yield('body-classes')">
        @stack('modals')
        <div class="min-h-full bg-gray-50">
            @yield('page')
        </div>
        @stack('scripts')
    </body>
</html>
