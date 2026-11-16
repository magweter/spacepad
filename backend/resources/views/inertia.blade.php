<!doctype html>
<html class="h-full" lang="{{ App::currentLocale() }}">
    <head>
        <meta charset="utf-8"/>
        <meta name="viewport" content="width=device-width, initial-scale=1"/>
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
        <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
        <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
        <link rel="manifest" href="/site.webmanifest">
        <link rel="shortcut icon" href="/favicon.ico">
        <meta name="theme-color" content="#14213D">
        <meta name="robots" content="noindex, nofollow">

        {{-- Same funnel scripts as layouts.blank: these pages are the top of the funnel. --}}
        @includeWhen(\App\Services\FunnelTracking::shouldRenderScripts(), 'googletagmanager::head')

        @vite(['resources/css/inertia.css', 'resources/js/inertia.js'])

        {!! RecaptchaV3::initJs() !!}

        @includeWhen(config('services.clarity.tag_code'), 'components.scripts.clarity')
        @include('components.scripts.faro')
        @inertiaHead
    </head>
    <body class="h-full">
        @includeWhen(\App\Services\FunnelTracking::shouldRenderScripts(), 'googletagmanager::body')
        @include('components.impersonation-banner')
        @inertia
    </body>
</html>
