@extends('layouts.blank')
@section('page')
    <nav class="bg-white border-b mb-8">
        <div class="mx-auto container px-4 sm:px-6">
            <div class="flex h-16 items-center justify-between px-4 sm:px-0">
                <a href="/" class="flex items-center">
                    <div class="flex-shrink-0 me-3">
                        <img class="h-7 w-7" src="/images/logo-black.svg" alt="Logo">
                    </div>
                    <span class="text-xl font-semibold text-black">Spacepad</span>
                </a>
                <div class="ml-4 flex items-center space-x-4">
                    @if(auth()->user()->hasPro())
                        <a href="mailto:support@spacepad.io" class="hidden md:block rounded-md px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-300 hover:text-black">
                            Need help?
                        </a>
                    @endif
                    <a href="https://github.com/magweter/spacepad/issues" class="hidden md:block rounded-md px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-300 hover:text-black">
                        Give feedback
                    </a>
                    <a href="https://spacepad.io" class="hidden md:block rounded-md px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-300 hover:text-black">
                        Visit website
                    </a>
                    @auth
                        <form action="{{ route('logout') }}" method="POST">
                            @csrf
                            <button type="submit" class="rounded-md px-3 py-2 text-sm border font-medium text-gray-700 hover:bg-gray-300 hover:text-black">
                                Log out
                            </button>
                        </form>
                    @endauth
                </div>
            </div>
        </div>
    </nav>

    <header class="mx-auto @yield('container_class', 'container') px-4 sm:px-6 py-6">
        <div class="flex gap-4 items-center">
            <h1 class="text-2xl/7 font-bold text-gray-900 sm:truncate sm:text-3xl sm:tracking-tight">@yield('title')</h1>
            @yield('actions')
        </div>
    </header>

    <main class="mx-auto @yield('container_class', 'container') px-4 sm:px-6 pb-16">
        @yield('content')
    </main>
@endsection
