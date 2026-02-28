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
                    @endphp
                    @if($workspaces->count() > 1)
                        <form action="{{ route('workspaces.switch') }}" method="POST" id="workspace-switch-form" class="flex items-center">
                            @csrf
                            <select 
                                id="workspace-select"
                                name="workspace_id" 
                                onchange="this.form.submit();"
                                class="rounded-md px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500 cursor-pointer"
                            >
                                @foreach($workspaces as $workspace)
                                    <option value="{{ $workspace->id }}" {{ ($selectedWorkspace?->id ?? $workspaces->first()->id) === $workspace->id ? 'selected' : '' }}>
                                        {{ $workspace->name }}
                                        @if($workspace->pivot->role === \App\Enums\WorkspaceRole::OWNER->value)
                                            (Owner)
                                        @elseif($workspace->pivot->role === \App\Enums\WorkspaceRole::ADMIN->value)
                                            (Admin)
                                        @endif
                                    </option>
                                @endforeach
                            </select>
                        </form>
                    @endif
                    @if(!session('impersonating') && auth()->user()->isAdmin() && !config('settings.is_self_hosted'))
                        <a href="{{ route('admin.index') }}" class="rounded-md px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-300 hover:text-black">
                            Admin
                        </a>
                    @endif
                    @if(auth()->user()->hasProForCurrentWorkspace())
                        <a href="{{ route('usage.index') }}" class="rounded-md px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-300 hover:text-black">
                            Usage
                        </a>
                        <button type="button" onclick="window.dispatchEvent(new CustomEvent('open-modal', { detail: 'manage-subscription' }))" class="rounded-md px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-300 hover:text-black">
                            Manage subscription
                        </button>
                        <a href="mailto:support@spacepad.io" class="hidden md:block rounded-md px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-300 hover:text-black">
                            Need help?
                        </a>
                    @endif
                    @auth
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
@endsection
