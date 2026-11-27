<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-ground">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#14213D">

    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
    <link rel="shortcut icon" href="/favicon.ico">

    <title>@yield('title', 'Error') - Spacepad</title>

    {{-- The portal's stylesheet, so error pages share its tokens, fonts and components. --}}
    @vite(['resources/css/inertia.css'])
    @include('components.scripts.faro')
</head>
<body class="flex min-h-full flex-col bg-ground font-sans text-sm leading-normal text-ink antialiased">
    <main class="flex flex-1 flex-col items-center justify-center px-4 py-12">
        <div class="flex w-full max-w-[440px] flex-col gap-6">
            <a href="{{ url('/') }}" class="inline-flex items-center gap-2.5 self-center text-lg leading-none font-bold tracking-tight text-navy">
                <img src="/images/spacepad-logo.svg" alt="" class="h-9 w-9 flex-none">
                Spacepad
            </a>

            <section class="sp-card sp-card-pad-lg flex flex-col items-start gap-4">
                <span class="sp-chip sp-chip-neutral font-mono">Error @yield('code')</span>
                <div class="flex flex-col gap-2">
                    <h1 class="font-display text-2xl font-bold tracking-tight text-ink">@yield('title')</h1>
                    <p class="text-ink-2">@yield('message')</p>
                </div>
                <div class="mt-2 flex flex-wrap gap-2">
                    @yield('actions')
                </div>
            </section>
        </div>
    </main>
</body>
</html>
