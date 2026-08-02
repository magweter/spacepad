@extends('layouts.blank')
@section('page')
    <nav class="bg-white border-b border-gray-200 mb-8">
        <div class="mx-auto container px-4 sm:px-6">
            <div class="flex h-16 items-center justify-between px-4 sm:px-0">
                <a href="/" class="flex items-center">
                    <div class="flex-shrink-0 me-3">
                        <img class="h-7 w-7" src="/images/logo-black.svg" alt="Logo">
                    </div>
                    <span class="text-xl font-semibold text-black">Spacepad</span>
                    @if(auth()->user()->hasProForCurrentWorkspace())
                        <span class="ml-2 inline-flex items-center rounded-md bg-blue-50 px-1.5 py-0.5 text-sm font-medium text-blue-700 ring-1 ring-inset ring-blue-600">Pro</span>
                    @endif
                </a>
                <div class="ml-4 flex items-center space-x-4">
                    @php
                        $workspaces = auth()->user()->workspaces()->withPivot('role')->get();
                        $selectedWorkspace = auth()->user()->getSelectedWorkspace();
                        $currentRole = $selectedWorkspace ? auth()->user()->workspaces->firstWhere('id', $selectedWorkspace->id)?->pivot?->role : null;
                    @endphp

                    {{-- Always shown, even with a single workspace: hiding it meant you could
                         never tell which workspace you were looking at. --}}
                    @if($selectedWorkspace)
                        <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                            <button type="button" @click="open = !open"
                                class="flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 hover:bg-gray-50">
                                <x-icons.building class="h-4 w-4 text-gray-400" />
                                <span class="max-w-[12rem] truncate">{{ $selectedWorkspace->name }}</span>
                                @if($currentRole)
                                    <span class="inline-flex items-center rounded bg-gray-100 px-1.5 py-0.5 text-xs font-medium text-gray-600">
                                        {{ \App\Enums\WorkspaceRole::fromPivot($currentRole)->label() }}
                                    </span>
                                @endif
                                <svg class="h-4 w-4 text-gray-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.17l3.71-3.94a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                                </svg>
                            </button>

                            <div x-show="open" x-cloak
                                class="absolute right-0 z-20 mt-2 w-72 origin-top-right rounded-md bg-white shadow-lg ring-1 ring-black/5 py-1">
                                @foreach($workspaces as $workspace)
                                    <form action="{{ route('workspaces.switch') }}" method="POST">
                                        @csrf
                                        <input type="hidden" name="workspace_id" value="{{ $workspace->id }}">
                                        <button type="submit"
                                            class="flex w-full items-center justify-between px-4 py-2 text-left text-sm {{ $selectedWorkspace->id === $workspace->id ? 'font-semibold text-gray-900 bg-gray-50' : 'text-gray-700 hover:bg-gray-50' }}">
                                            <span class="truncate">{{ $workspace->name }}</span>
                                            @if($selectedWorkspace->id === $workspace->id)
                                                <span class="text-blue-600">&checkmark;</span>
                                            @endif
                                        </button>
                                    </form>
                                @endforeach

                                <div class="my-1 border-t border-gray-100"></div>

                                <a href="{{ route('workspaces.members') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                    Team members
                                </a>

                                <form action="{{ route('workspaces.store') }}" method="POST" x-data="{ naming: false }">
                                    @csrf
                                    <button type="button" x-show="!naming" @click="naming = true"
                                        class="block w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-50">
                                        + New workspace
                                    </button>
                                    <div x-show="naming" x-cloak class="px-4 py-2">
                                        <input type="text" name="name" placeholder="Workspace name" required
                                            class="block w-full px-2 py-1 text-sm border rounded-md border-gray-300 focus:border-blue-500 focus:ring-blue-500">
                                        <button type="submit" class="mt-2 w-full rounded-md bg-oxford px-2 py-1 text-sm font-semibold text-white">
                                            Create
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    @endif

                    <a href="{{ route('workspaces.members') }}" class="rounded-md px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-300 hover:text-black">
                        Team
                    </a>
                    @if(!session('impersonating') && auth()->user()->isAdmin() && !config('settings.is_self_hosted'))
                        <a href="{{ route('admin.index') }}" class="rounded-md px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-300 hover:text-black">
                            Admin
                        </a>
                    @endif
                    @auth
                        <a href="{{ route('profile.show') }}" class="rounded-md px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-300 hover:text-black">
                            Account
                        </a>
                        <form action="{{ route('logout') }}" method="POST">
                            @csrf
                            <button type="submit" class="rounded-md px-3 py-2 text-sm border border-gray-300 font-medium text-gray-700 hover:bg-gray-300 hover:text-black">
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

    @include('components.modals.manage-subscription')
    @if(!config('settings.is_self_hosted'))
        <x-help.faq />
    @endif
@endsection
