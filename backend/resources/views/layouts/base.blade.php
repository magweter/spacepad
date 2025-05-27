@extends('layouts.blank')
@section('page')
    <div class="pb-8">
        <nav class="border-b">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div>
                    <div class="flex h-16 items-center justify-between px-4 sm:px-0">
                        <a href="/" class="flex items-center">
                            <div class="flex-shrink-0 me-3">
                                <img class="h-7 w-7" src="/images/logo-black.svg" alt="Logo">
                            </div>
                            <span class="text-xl font-semibold text-black">Spacepad</span>
                        </a>
                        <div>
                            @auth
                                <div class="ml-4 flex items-center md:ml-6">
                                    <form action="{{ route('logout') }}" method="POST">
                                        @csrf
                                        <button type="submit" class="rounded-md px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-300 hover:text-black">
                                            Log out
                                        </button>
                                    </form>
                                </div>
                            @endauth
                        </div>
                    </div>
                </div>
            </div>
        </nav>
    </div>

    <header class="py-6">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <h1 class="text-3xl font-semibold tracking-tight text-black">@yield('title')</h1>
        </div>
    </header>

    <main>
        <div class="mx-auto max-w-7xl px-4 pb-12 sm:px-6 lg:px-8">
            <div class="rounded-lg bg-white px-5 py-6 border sm:px-6 min-h-80">
                @yield('content')
            </div>
        </div>
    </main>
@endsection
