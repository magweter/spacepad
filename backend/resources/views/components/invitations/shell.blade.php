@props(['heading', 'subheading' => null])

<div class="flex min-h-screen flex-col justify-center px-6 py-12">
    <div class="mx-auto w-full max-w-md">
        <div class="flex items-center justify-center mb-8">
            <img class="h-9 w-9 me-2.5" src="/images/spacepad-logo.svg" alt="">
            <span class="text-lg leading-none font-bold tracking-tight text-oxford">Spacepad</span>
        </div>

        <div class="bg-white shadow-sm rounded-lg border border-gray-200 px-6 py-8">
            <h1 class="text-lg font-semibold text-gray-900 mb-2">{{ $heading }}</h1>

            @if($subheading)
                <p class="text-sm text-gray-500 mb-6">{{ $subheading }}</p>
            @endif

            {{ $slot }}
        </div>
    </div>
</div>
