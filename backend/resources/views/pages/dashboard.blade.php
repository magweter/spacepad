@extends('layouts.base')
@section('title', 'Management dashboard')

@section('actions')
    {{-- Workspace Selector and Connect Code --}}
    @if($workspaces->count() > 1 || $connectCode)
        <div class="items-center flex ml-auto gap-4">
            @if($workspaces->count() > 1)
                <form action="{{ route('workspaces.switch') }}" method="POST" id="workspace-switch-form">
                    @csrf
                    <div class="flex border border-dashed rounded-lg px-4 h-14 items-center border-gray-400">
                        <label for="workspace-select" class="text-sm font-semibold text-gray-900 mr-4 flex items-center">Workspace</label>
                        <select 
                            id="workspace-select"
                            name="workspace_id" 
                            onchange="this.form.submit();"
                            class="flex-1 text-sm font-medium text-gray-900 bg-transparent border bg-white rounded-lg p-1 focus:ring-0 focus:outline-none cursor-pointer"
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
                    </div>
                </form>
            @endif
            @if((auth()->user()->hasAnyDisplay() || auth()->user()->workspaces()->count() > 1) && $connectCode)
                <div class="flex border border-dashed rounded-lg px-4 h-14 items-center border-gray-400">
                    <h3 class="text-sm font-semibold text-gray-900 mr-8 flex items-center">Connect code</h3>
                    <div class="flex-1 text-sm text-gray-500">
                        <p class="font-mono">{{ chunk_split($connectCode, 3, ' ') }}</p>
                    </div>
                </div>
            @endif
        </div>
    @endif
@endsection

@section('content')
    @php
        $isSelfHosted = config('settings.is_self_hosted');
        $checkout = auth()->user()->getCheckoutUrl(route('billing.thanks'));
        $showLicenseModal = $isSelfHosted && !auth()->user()->hasPro();
    @endphp

    {{-- Session Status Alert --}}
    <x-alerts.alert :errors="$errors" />

    {{-- Google Workspace Booking Method Selection Warnings --}}
    @foreach($googleAccounts as $googleAccount)
        @if($googleAccount->permission_type === \App\Enums\PermissionType::WRITE && $googleAccount->isBusiness() && $googleAccount->booking_method === null)
            <div class="mb-4 rounded-md bg-yellow-50 ring-1 ring-inset ring-yellow-600 p-4 flex items-start gap-4" x-data>
                <div class="flex-shrink-0 mt-1">
                    <span class="inline-flex items-center justify-center h-10 w-10 rounded-full bg-yellow-100">
                        <svg class="h-6 w-6 text-yellow-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M12 9v2m0 4h.01M5.07 20A9.938 9.938 0 0 1 2 12C2 6.48 6.48 2 12 2c5.52 0 10 4.48 10 10a9.938 9.938 0 0 1-3.07 8" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </span>
                </div>
                <div class="flex-1">
                    <h3 class="text-md font-semibold text-yellow-900 mb-1">Booking Method Required</h3>
                    <p class="text-sm text-yellow-800 mb-1">
                        Please select a booking method for your Google Workspace account <strong>{{ $googleAccount->name }}</strong> ({{ $googleAccount->email }}).
                    </p>
                </div>
                <div class="flex-shrink-0 ml-4 mt-2">
                    <button 
                        type="button"
                        @click="$dispatch('open-google-booking-method-modal', '{{ $googleAccount->id }}')"
                        class="inline-flex items-center rounded-md bg-yellow-600 px-3 py-2 text-sm font-semibold text-white shadow-xs hover:bg-yellow-700">
                        Select booking method
                    </button>
                </div>
            </div>
        @endif
    @endforeach

    {{-- Service Account Warnings --}}
    @foreach($googleAccounts as $googleAccount)
        @if($googleAccount->isBusiness() && $googleAccount->booking_method === \App\Enums\GoogleBookingMethod::SERVICE_ACCOUNT && !$googleAccount->service_account_file_path)
            <div class="mb-4 rounded-md bg-yellow-50 ring-1 ring-inset ring-yellow-600 p-4 flex items-start gap-4" x-data>
                <div class="flex-shrink-0 mt-1">
                    <span class="inline-flex items-center justify-center h-10 w-10 rounded-full bg-yellow-100">
                        <svg class="h-6 w-6 text-yellow-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M12 9v2m0 4h.01M5.07 20A9.938 9.938 0 0 1 2 12C2 6.48 6.48 2 12 2c5.52 0 10 4.48 10 10a9.938 9.938 0 0 1-3.07 8" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </span>
                </div>
                <div class="flex-1">
                    <h3 class="text-md font-semibold text-yellow-900 mb-1">Service Account Required</h3>
                    <p class="text-sm text-yellow-800 mb-1">
                        The Google Workspace account <strong>{{ $googleAccount->name }}</strong> ({{ $googleAccount->email }}) is configured to use service account booking but the service account file has not been uploaded yet. Please upload your service account file to enable room bookings.
                    </p>
                </div>
                <div class="flex-shrink-0 ml-4 mt-2">
                    <button 
                        type="button"
                        @click="$dispatch('open-service-account-modal', { googleAccountId: '{{ $googleAccount->id }}' })"
                        class="inline-flex items-center rounded-md bg-yellow-600 px-3 py-2 text-sm font-semibold text-white shadow-xs hover:bg-yellow-700">
                        Set up service account
                    </button>
                </div>
            </div>
        @endif
    @endforeach

    {{-- License Key Modal --}}
    <x-modals.license-key />

    {{-- Commercial Banner --}}
    @if(! auth()->user()->hasProForCurrentWorkspace() && auth()->user()->hasAnyDisplay())
        <div class="mb-4 rounded-lg bg-indigo-50 border border-indigo-200 p-4 flex items-start gap-4">
            <div class="flex-shrink-0 mt-1">
                <span class="inline-flex items-center justify-center h-10 w-10 rounded-full bg-indigo-100">
                    <x-icons.settings class="h-6 w-6 text-indigo-500" />
                </span>
            </div>
            <div class="flex-1">
                <h3 class="text-md font-semibold text-indigo-900 mb-1">Unlock all features</h3>
                <p class="text-sm text-indigo-800 mb-1">
                    Upgrade to Pro to create multiple displays, book on-display, customize or hide meeting titles, use logos and backgrounds, enable check-in and more!
                </p>
                <p class="text-sm text-indigo-700 mb-0">
                    <a href="https://spacepad.io/#features" target="_blank" class="underline hover:text-indigo-900 inline-block">See all Pro features</a> or <a href="https://spacepad.io/pricing" target="_blank" class="underline hover:text-indigo-900 inline-block">see pricing</a>.
                </p>
            </div>
            <div class="flex-shrink-0 ml-4 mt-2">
                @if($isSelfHosted)
                    <button type="button" x-data @click="$dispatch('open-modal', 'license-key')" class="inline-flex items-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-xs hover:bg-indigo-700">
                        Try Pro 14 days for free
                    </button>
                @else
                    <x-lemon-button :href="$checkout" class="inline-flex items-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-xs hover:bg-indigo-700">
                        Try Pro 14 days for free
                    </x-lemon-button>
                @endif
            </div>
        </div>
    @endif

    <div class="grid gap-4 grid-cols-12 min-h-[600px]">
        <x-cards.card class="col-span-12 xl:col-span-8">
            {{-- Tabs --}}
            <div class="border-b border-gray-200 mb-6">
                <nav class="-mb-px flex space-x-8" aria-label="Tabs">
                    <button onclick="switchTab('displays')" id="tab-displays" class="tab-button border-b-2 border-blue-600 pb-4 px-1 text-sm font-medium text-blue-600 whitespace-nowrap">
                        Displays
                    </button>
                    @if(auth()->user()->hasProForCurrentWorkspace())
                        <button onclick="switchTab('boards')" id="tab-boards" class="tab-button border-b-2 border-transparent pb-4 px-1 text-sm font-medium text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap">
                            Boards <span class="ml-1 inline-flex items-center rounded-md bg-green-50 px-1.5 py-0.5 text-xs font-medium text-green-700 ring-1 ring-inset ring-green-600/20">New</span>
                        </button>
                    @endif
                </nav>
            </div>

            {{-- Displays Tab Content --}}
            <div id="tab-content-displays" class="tab-content">
                <div class="sm:flex sm:items-center mb-4">
                    <div class="sm:flex-auto">
                        <h2 class="text-lg font-semibold leading-6 text-gray-900">Displays</h2>
                        <p class="mt-1 text-sm text-gray-500">
                            Overview of your displays and their status.
                        </p>
                    </div>
                    <div class="mt-4 sm:ml-16 sm:mt-0 sm:flex-none flex items-center gap-2">
                        @if(auth()->user()->hasAnyDisplay() || auth()->user()->workspaces()->count() > 1)
                            <button type="button" onclick="openConnectModal()" class="inline-flex items-center gap-x-1.5 rounded-md bg-oxford px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-oxford-600 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-oxford-600">
                                <x-icons.display class="h-4 w-4" />
                                How to connect a tablet
                            </button>
                        @endif
                        @if(auth()->user()->can('create', \App\Models\Display::class))
                            @if(auth()->user()->shouldUpgradeForCurrentWorkspace())
                                <span class="inline-flex items-center rounded-md bg-gray-100 px-3 py-2 text-center text-sm font-semibold text-gray-400 shadow-sm ring-1 ring-inset ring-gray-200 cursor-not-allowed" title="Upgrade to Pro to create more displays">
                                    <x-icons.plus class="h-5 w-5 mr-1" />
                                    Create new display <span class="ml-2 inline-flex items-center rounded-md bg-blue-50 px-1.5 py-0.5 text-xs font-medium text-blue-700 ring-1 ring-inset ring-blue-600">Pro</span>
                                </span>
                            @else
                                <a href="{{ route('displays.create') }}" class="inline-flex items-center rounded-md bg-oxford px-3 py-2 text-center text-sm font-semibold text-white">
                                    <x-icons.plus class="h-5 w-5 mr-1" />
                                    Create new display
                                </a>
                            @endif
                        @endif
                    </div>
                </div>

            {{-- Connect Instructions Modal --}}
            <div id="connectModal" class="relative z-10 hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
                <div class="fixed inset-0 bg-gray-500 opacity-75 transition-opacity"></div>

                <div class="fixed inset-0 z-10 overflow-y-auto">
                    <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
                        <div class="relative transform overflow-hidden rounded-lg bg-white px-4 pb-4 pt-5 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg sm:p-6">
                            <div>
                                <div class="mt-2 text-center">
                                    <h3 class="text-lg font-semibold leading-6 text-gray-900" id="modal-title">Instructions on connecting a new device</h3>
                                    <div class="mt-2 mx-auto max-w-md">
                                        <p class="text-sm text-gray-700">Connect a new device like a tablet or phone by downloading the app from the <a target="_blank" href="https://play.google.com/store/apps/details?id=com.magweter.spacepad" class="text-blue-600 hover:text-blue-500">Play Store</a> or <a target="_blank" href="https://apps.apple.com/nl/app/spacepad/id6745528995" class="text-blue-600 hover:text-blue-500">App Store</a>.</p>
                                    </div>
                                    @if(config('settings.is_self_hosted'))
                                        <div class="mt-6 mx-auto max-w-md text-center">
                                            <p class="text-sm text-gray-700">Select 'self-hosted' and enter the following url:</p>
                                        </div>
                                        <div class="mt-4 p-4 bg-gray-50 rounded-lg">
                                            <p class="text-lg font-mono text-center">{{ config('app.url') }}</p>
                                        </div>
                                    @endif
                                    <div class="mt-6 mx-auto max-w-md text-center">
                                        <p class="text-sm text-gray-700">Enter the following connect code:</p>
                                    </div>
                                    <div class="mt-4 p-4 bg-gray-50 rounded-lg">
                                        <p class="text-2xl font-mono text-center">{{ chunk_split($connectCode, 3, ' ') }}</p>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-5 sm:mt-8">
                                <button type="button" onclick="closeConnectModal()" class="inline-flex w-full justify-center rounded-md bg-oxford px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-oxford-600 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-oxford-600">Close</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

                <div class="mt-6 flow-root">
                    <div class="-mx-4 -my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
                        <div class="inline-block min-w-full py-2 align-middle sm:px-6 lg:px-8">
                            <table class="min-w-full divide-y divide-gray-300">
                                <thead>
                                <tr>
                                    <th scope="col" class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900 sm:pl-0">Name</th>
                                    <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Account</th>
                                    <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Status</th>
                                    <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Activity</th>
                                    <th scope="col" class="relative py-3.5 pr-4 pl-3 sm:pr-0">
                                        <span class="sr-only">Actions</span>
                                    </th>
                                </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200" id="displays-table">
                                @forelse($displays as $display)
                                    <x-displays.table-row :display="$display" />
                                @empty
                                    <tr>
                                        <td colspan="5" class="py-16 text-center">
                                            <div class="flex flex-col items-center justify-center">
                                                <x-icons.display class="h-12 w-12 text-orange mb-3" />
                                                <h3 class="mb-2 text-md font-semibold text-gray-900">
                                                    One more step and you're set up
                                                </h3>
                                                <p class="mb-6 text-sm text-gray-500 max-w-sm">Pick the calendar or room you would like to synchronize. You are able to connect multiple tablets to one display.</p>
                                                @if(! $isSelfHosted && auth()->user()->shouldUpgradeForCurrentWorkspace())
                                                    <span class="inline-flex items-center rounded-md bg-gray-100 px-3 py-2 text-center text-sm font-semibold text-gray-400 shadow-sm ring-1 ring-inset ring-gray-200 cursor-not-allowed" title="Upgrade to Pro to create more displays">
                                                        <x-icons.plus class="h-5 w-5 mr-1" /> Create new display <span class="ml-2 inline-flex items-center rounded-md bg-blue-50 px-1.5 py-0.5 text-xs font-medium text-blue-700 ring-1 ring-inset ring-blue-600">Pro</span>
                                                    </span>
                                                @elseif($isSelfHosted && auth()->user()->shouldUpgradeForCurrentWorkspace())
                                                    <span class="inline-flex items-center rounded-md bg-gray-100 px-3 py-2 text-center text-sm font-semibold text-gray-400 shadow-sm ring-1 ring-inset ring-gray-200 cursor-not-allowed" title="Upgrade to Pro to create more displays">
                                                        <x-icons.plus class="h-5 w-5 mr-1" /> Create new display <span class="ml-2 inline-flex items-center rounded-md bg-blue-50 px-1.5 py-0.5 text-xs font-medium text-blue-700 ring-1 ring-inset ring-blue-600">Pro</span>
                                                    </span>
                                                @else
                                                    <a href="{{ route('displays.create') }}" class="inline-flex items-center rounded-md bg-oxford px-3 py-2 text-center text-sm font-semibold text-white">
                                                        <x-icons.plus class="h-5 w-5 mr-1" />
                                                        Create new display
                                                    </a>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Boards Tab Content --}}
            @if(auth()->user()->hasProForCurrentWorkspace())
                <div id="tab-content-boards" class="tab-content hidden">
                    <div class="sm:flex sm:items-center mb-4">
                        <div class="sm:flex-auto">
                            <h2 class="text-lg font-semibold leading-6 text-gray-900">Boards</h2>
                            <p class="mt-1 text-sm text-gray-500">
                                Meeting room availability overviews for big screens.
                            </p>
                        </div>
                        <div class="mt-4 sm:ml-16 sm:mt-0 sm:flex-none">
                            <a href="{{ route('boards.create') }}" class="inline-flex items-center rounded-md bg-oxford px-3 py-2 text-center text-sm font-semibold text-white">
                                <x-icons.plus class="h-5 w-5 mr-1" />
                                Create new board
                            </a>
                        </div>
                    </div>

                    <div class="mt-6 flow-root">
                        <div class="-mx-4 -my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
                            <div class="inline-block min-w-full py-2 align-middle sm:px-6 lg:px-8">
                                <table class="min-w-full divide-y divide-gray-300">
                                    <thead>
                                    <tr>
                                        <th scope="col" class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900 sm:pl-0">Name</th>
                                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Displays</th>
                                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Type</th>
                                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Created by</th>
                                        <th scope="col" class="relative py-3.5 pr-4 pl-3 sm:pr-0">
                                            <span class="sr-only">Actions</span>
                                        </th>
                                    </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200">
                                    @forelse($boards as $board)
                                        <tr>
                                            <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-0">
                                                {{ $board->name }}
                                            </td>
                                            <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                                                {{ $board->display_count }} {{ $board->display_count === 1 ? 'display' : 'displays' }}
                                            </td>
                                            <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                                                @if($board->show_all_displays)
                                                    <span class="inline-flex items-center rounded-md bg-green-50 px-2 py-1 text-xs font-medium text-green-700 ring-1 ring-inset ring-green-600/20">
                                                        Show all
                                                    </span>
                                                @else
                                                    <span class="inline-flex items-center rounded-md bg-blue-50 px-2 py-1 text-xs font-medium text-blue-700 ring-1 ring-inset ring-blue-600/20">
                                                        Selected
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                                                {{ $board->user->name }}
                                            </td>
                                            <td class="relative whitespace-nowrap py-4 pl-3 pr-4 text-right text-sm font-medium sm:pr-0">
                                                <div class="flex justify-end gap-x-2">
                                                    <a href="{{ route('boards.show', $board) }}" target="_blank" class="inline-flex items-center rounded-md bg-white px-2.5 py-1.5 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50" title="Open board in new tab">
                                                        <x-icons.external class="h-4 w-4" />
                                                    </a>
                                                    @can('update', $board)
                                                        <a href="{{ route('boards.edit', $board) }}" class="inline-flex items-center rounded-md bg-white px-2.5 py-1.5 text-sm font-semibold text-blue-600 shadow-sm ring-1 ring-inset ring-blue-300 hover:bg-blue-50" title="Edit board">
                                                            <x-icons.settings class="h-4 w-4" />
                                                        </a>
                                                    @endcan
                                                    @can('delete', $board)
                                                        <form action="{{ route('boards.destroy', $board) }}" method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this board?');">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="inline-flex items-center rounded-md bg-white px-2.5 py-1.5 text-sm font-semibold text-red-600 shadow-sm ring-1 ring-inset ring-red-300 hover:bg-red-50" title="Delete board">
                                                                <x-icons.trash class="h-4 w-4" />
                                                            </button>
                                                        </form>
                                                    @endcan
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="py-16 text-center">
                                                <div class="flex flex-col items-center justify-center">
                                                    <x-icons.display class="h-12 w-12 text-orange mb-3" />
                                                    <h3 class="mb-2 text-md font-semibold text-gray-900">
                                                        No boards yet
                                                    </h3>
                                                    <p class="mb-6 text-sm text-gray-500 max-w-sm">Create your first board to display room availability on a big screen.</p>
                                                    <a href="{{ route('boards.create') }}" class="inline-flex items-center rounded-md bg-oxford px-3 py-2 text-center text-sm font-semibold text-white">
                                                        <x-icons.plus class="h-5 w-5 mr-1" />
                                                        Create new board
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </x-cards.card>
        
        <x-cards.card class="col-span-12 xl:col-span-4 space-y-6">
            <div>
                <h2 class="text-lg font-semibold leading-6 text-gray-900">Accounts</h2>
                <p class="mt-1 text-sm text-gray-500">The accounts used to connect to calendars and rooms.</p>
            </div>
            <div>
                <div class="flex flex-col md:flex-row gap-4">
                    @if(config('services.microsoft.enabled'))
                        <button 
                            type="button"
                            onclick="window.dispatchEvent(new CustomEvent('open-permission-modal', { detail: { provider: 'outlook' } }))"
                            class="grow flex items-center justify-center gap-3 rounded-lg border border-gray-300 bg-white p-4 shadow-sm hover:border-blue-500 hover:shadow-md transition-all duration-200">
                            <x-icons.microsoft class="h-6 w-6" />
                            <span class="font-medium text-gray-900">Microsoft</span>
                        </button>
                    @endif

                    @if(config('services.google.enabled'))
                        <button 
                            type="button"
                            onclick="window.dispatchEvent(new CustomEvent('open-permission-modal', { detail: { provider: 'google' } }))"
                            class="grow flex items-center justify-center gap-3 rounded-lg border border-gray-300 bg-white p-4 shadow-sm hover:border-blue-500 hover:shadow-md transition-all duration-200">
                            <x-icons.google class="h-6 w-6" />
                            <span class="font-medium text-gray-900">Google</span>
                        </button>
                    @endif

                    @if(config('services.caldav.enabled'))
                        <a href="{{ route('caldav-accounts.create') }}"
                           class="grow flex items-center justify-center gap-3 rounded-lg border border-gray-300 bg-white p-4 shadow-sm hover:border-blue-500 hover:shadow-md transition-all duration-200">
                            <x-icons.calendar class="h-6 w-6 text-gray-600" />
                            <span class="font-medium text-gray-900">CalDAV</span>
                        </a>
                    @endif
                </div>
            </div>
            <div class="relative">
                <div class="absolute inset-0 flex items-center" aria-hidden="true">
                    <div class="w-full border-t border-gray-300"></div>
                </div>
                <div class="relative flex justify-center">
                    <span class="bg-white px-2 text-sm text-gray-500">Connected accounts</span>
                </div>
            </div>
            @if($outlookAccounts->isEmpty() && $googleAccounts->isEmpty() && $caldavAccounts->isEmpty())
                <div class="py-12 text-center">
                    <div class="flex flex-col items-center justify-center">
                        <h3 class="mb-2 text-md font-semibold text-gray-900">
                            No accounts connected yet
                        </h3>
                        <p class="mb-0 text-sm text-gray-500 max-w-sm">
                            Connect a calendar account above to get started. You can connect Microsoft, Google, or CalDAV accounts.
                        </p>
                    </div>
                </div>
            @else
                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-1 gap-4">
                    @foreach($outlookAccounts as $outlookAccount)
                    <div class="relative flex items-center space-x-4 rounded-lg border border-gray-300 bg-white px-5 py-4 shadow-sm focus-within:ring-2 focus-within:ring-blue-500 focus-within:ring-offset-2 hover:border-gray-400">
                        @if($outlookAccount->calendars->isEmpty())
                            <form action="{{ route('outlook-accounts.delete', $outlookAccount) }}" method="POST" class="absolute top-4.5 right-2 z-10">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="group p-1 rounded hover:bg-gray-100" title="Disconnect">
                                    <x-icons.trash class="h-4 w-4 text-gray-400 group-hover:text-red-600" />
                                </button>
                            </form>
                        @else
                            <span class="flex absolute top-4.5 right-2 z-10 group cursor-not-allowed" title="Delete all connected displays first before disconnecting the account">
                                <span class="p-1 rounded">
                                    <x-icons.trash class="h-4 w-4 text-gray-300" />
                                </span>
                            </span>
                        @endif
                        <div class="flex-shrink-0 px-1">
                            <x-icons.microsoft class="h-12 w-12" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <span class="absolute inset-0" aria-hidden="true"></span>
                            <div class="flex items-center gap-2 flex-wrap">
                                <p class="text-md font-medium text-gray-900">{{ $outlookAccount->name }}</p>
                            </div>
                            <div class="truncate text-sm text-gray-500 flex items-center gap-2 flex-wrap">
                                <span>{{ $outlookAccount->email }}</span>
                            </div>
                            <div class="truncate text-sm text-gray-500 flex items-center gap-2 mt-1 flex-wrap">
                                <p class="mt-0.5 whitespace-nowrap rounded-md bg-green-50 px-1.5 py-0.5 text-xs font-medium text-green-700 ring-1 ring-inset ring-green-600">Connected</p>
                                <p class="mt-0.5 whitespace-nowrap rounded-md px-1.5 py-0.5 text-xs font-medium ring-1 ring-inset {{ $outlookAccount->isBusiness() ? 'bg-purple-50 text-purple-700 ring-purple-600' : 'bg-gray-50 text-gray-700 ring-gray-600' }}">
                                    {{ $outlookAccount->isBusiness() ? 'Microsoft 365' : 'Personal' }}
                                </p>
                                @if($outlookAccount->permission_type)
                                    <p class="mt-0.5 whitespace-nowrap rounded-md px-1.5 py-0.5 text-xs font-medium ring-1 ring-inset {{ $outlookAccount->permission_type === \App\Enums\PermissionType::WRITE ? 'bg-blue-50 text-blue-700 ring-blue-600' : 'bg-gray-50 text-gray-700 ring-gray-600' }}">
                                        {{ $outlookAccount->permission_type->label() }}
                                    </p>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
                @foreach($googleAccounts as $googleAccount)
                    <div class="relative flex items-center space-x-4 rounded-lg border border-gray-300 bg-white px-5 py-4 shadow-sm focus-within:ring-2 focus-within:ring-blue-500 focus-within:ring-offset-2 hover:border-gray-400">
                        @if($googleAccount->calendars->isEmpty())
                            <form action="{{ route('google-accounts.delete', $googleAccount) }}" method="POST" class="absolute top-4.5 right-2 z-10">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="group p-1 rounded hover:bg-gray-100" title="Disconnect">
                                    <x-icons.trash class="h-4 w-4 text-gray-400 group-hover:text-red-600" />
                                </button>
                            </form>
                        @else
                            <span class="flex absolute top-4.5 right-2 z-10 group cursor-not-allowed" title="Delete all connected displays first before disconnecting the account">
                                <span class="p-1 rounded">
                                    <x-icons.trash class="h-4 w-4 text-gray-300" />
                                </span>
                            </span>
                        @endif
                        <div class="flex-shrink-0 px-1">
                            <x-icons.google class="h-12 w-12" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <span class="absolute inset-0" aria-hidden="true"></span>
                            <div class="flex items-center gap-2 flex-wrap">
                                <p class="text-md font-medium text-gray-900">{{ $googleAccount->name }}</p>
                            </div>
                            <div class="truncate text-sm text-gray-500 flex items-center gap-2 flex-wrap">
                                <span>{{ $googleAccount->email }}</span>
                            </div>
                            <div class="truncate text-sm text-gray-500 flex items-center gap-2 mt-1 flex-wrap">
                                <p class="mt-0.5 whitespace-nowrap rounded-md bg-green-50 px-1.5 py-0.5 text-xs font-medium text-green-700 ring-1 ring-inset ring-green-600">Connected</p>
                                <p class="mt-0.5 whitespace-nowrap rounded-md px-1.5 py-0.5 text-xs font-medium ring-1 ring-inset {{ $googleAccount->isBusiness() ? 'bg-purple-50 text-purple-700 ring-purple-600' : 'bg-gray-50 text-gray-700 ring-gray-600' }}">
                                    {{ $googleAccount->isBusiness() ? 'Workspace' : 'Personal' }}
                                </p>
                                @if($googleAccount->permission_type)
                                    <p class="mt-0.5 whitespace-nowrap rounded-md px-1.5 py-0.5 text-xs font-medium ring-1 ring-inset {{ $googleAccount->permission_type === \App\Enums\PermissionType::WRITE ? 'bg-blue-50 text-blue-700 ring-blue-600' : 'bg-gray-50 text-gray-700 ring-gray-600' }}">
                                        {{ $googleAccount->permission_type->label() }}
                                    </p>
                                @endif
                                @if($googleAccount->isBusiness() && $googleAccount->booking_method)
                                    <p class="mt-0.5 whitespace-nowrap rounded-md px-1.5 py-0.5 text-xs font-medium ring-1 ring-inset {{ $googleAccount->booking_method === \App\Enums\GoogleBookingMethod::SERVICE_ACCOUNT ? 'bg-orange-50 text-orange-700 ring-orange-600' : 'bg-indigo-50 text-indigo-700 ring-indigo-600' }}">
                                        {{ $googleAccount->booking_method === \App\Enums\GoogleBookingMethod::SERVICE_ACCOUNT ? 'Service Account' : 'User Account' }}
                                    </p>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
                @foreach($caldavAccounts as $caldavAccount)
                    <div class="relative flex items-center space-x-4 rounded-lg border border-gray-300 bg-white px-5 py-4 shadow-sm focus-within:ring-2 focus-within:ring-blue-500 focus-within:ring-offset-2 hover:border-gray-400">
                        @if($caldavAccount->calendars->isEmpty())
                            <form action="{{ route('caldav-accounts.delete', $caldavAccount) }}" method="POST" class="absolute top-4.5 right-2 z-10">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="group p-1 rounded hover:bg-gray-100" title="Disconnect">
                                    <x-icons.trash class="h-4 w-4 text-gray-400 group-hover:text-red-600" />
                                </button>
                            </form>
                        @else
                            <span class="flex absolute top-4.5 right-2 z-10 group cursor-not-allowed" title="Delete all connected displays first before disconnecting the account">
                                <span class="p-1 rounded">
                                    <x-icons.trash class="h-4 w-4 text-gray-300" />
                                </span>
                            </span>
                        @endif
                        <div class="flex-shrink-0 px-1">
                            <x-icons.calendar class="h-12 w-12" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <span class="absolute inset-0" aria-hidden="true"></span>
                            <div class="flex items-center gap-2 flex-wrap">
                                <p class="text-md font-medium text-gray-900">{{ $caldavAccount->name }}</p>
                            </div>
                            <div class="truncate text-sm text-gray-500 flex items-center gap-2 flex-wrap">
                                <span>{{ $caldavAccount->email }}</span>
                            </div>
                            <div class="truncate text-sm text-gray-500 flex items-center gap-2 mt-1 flex-wrap">
                                <p class="mt-0.5 whitespace-nowrap rounded-md bg-green-50 px-1.5 py-0.5 text-xs font-medium text-green-700 ring-1 ring-inset ring-green-600">Connected</p>
                                @if($caldavAccount->permission_type)
                                    <p class="mt-0.5 whitespace-nowrap rounded-md px-1.5 py-0.5 text-xs font-medium ring-1 ring-inset {{ $caldavAccount->permission_type === \App\Enums\PermissionType::WRITE ? 'bg-blue-50 text-blue-700 ring-blue-600' : 'bg-gray-50 text-gray-700 ring-gray-600' }}">
                                        {{ $caldavAccount->permission_type->label() }}
                                    </p>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
                </div>
            @endif
        </x-cards.card>
    </div>

    {{-- Server Info (Self-hosted only) --}}
    @if($isSelfHosted)
        <div class="mt-8 flex flex-wrap items-center gap-3 text-sm text-gray-500">
            <span class="inline-flex items-center rounded-md bg-gray-100 px-2.5 py-1.5 text-xs font-medium text-gray-600">
                Self-hosted
            </span>
            @if($version)
                <span class="inline-flex items-center rounded-md bg-gray-100 px-2.5 py-1.5 text-xs font-medium text-gray-600">
                    v{{ $version }}
                </span>
            @endif
            @if($appUrl)
                <span class="inline-flex items-center rounded-md bg-gray-100 px-2.5 py-1.5 text-xs font-medium text-gray-600">
                    {{ parse_url($appUrl, PHP_URL_HOST) }} ({{ $appEnv }})
                </span>
            @endif
        </div>
    @endif
@endsection

@push('scripts')
    <script>
        function openConnectModal() {
            document.getElementById('connectModal').classList.remove('hidden');
        }

        function closeConnectModal() {
            document.getElementById('connectModal').classList.add('hidden');
        }

        // Close modal when clicking outside
        document.getElementById('connectModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeConnectModal();
            }
        });

        // Close modal when pressing Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeConnectModal();
            }
        });

        // Show service account modal if needed
        @if(session('open-service-account-modal'))
            window.addEventListener('DOMContentLoaded', function() {
                window.dispatchEvent(new CustomEvent('open-service-account-modal', {
                    detail: { googleAccountId: '{{ session('open-service-account-modal') }}' }
                }));
            });
        @endif

        // Show booking method modal if needed (after connecting Google Workspace account with write permission)
        @if(session('open-google-booking-method-modal'))
            window.addEventListener('DOMContentLoaded', function() {
                window.dispatchEvent(new CustomEvent('open-google-booking-method-modal', {
                    detail: '{{ session('open-google-booking-method-modal') }}'
                }));
            });
        @endif

        // Tab switching functionality
        function switchTab(tabName, updateUrl = true) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.add('hidden');
            });

            // Remove active state from all tabs
            document.querySelectorAll('.tab-button').forEach(button => {
                button.classList.remove('border-blue-600', 'text-blue-600');
                button.classList.add('border-transparent', 'text-gray-500');
            });

            // Show selected tab content
            const selectedContent = document.getElementById('tab-content-' + tabName);
            if (selectedContent) {
                selectedContent.classList.remove('hidden');
            }

            // Activate selected tab button
            const selectedTab = document.getElementById('tab-' + tabName);
            if (selectedTab) {
                selectedTab.classList.remove('border-transparent', 'text-gray-500');
                selectedTab.classList.add('border-blue-600', 'text-blue-600');
            }

            // Update URL with tab parameter
            if (updateUrl) {
                const url = new URL(window.location);
                if (tabName === 'displays') {
                    // Remove tab parameter for default tab
                    url.searchParams.delete('tab');
                } else {
                    url.searchParams.set('tab', tabName);
                }
                window.history.pushState({ tab: tabName }, '', url);
            }
        }

        // Restore active tab on page load from URL
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const tabFromUrl = urlParams.get('tab');
            const defaultTab = 'displays';
            const tabToShow = tabFromUrl || defaultTab;
            
            // Check if the tab exists (e.g., boards tab might not exist for non-Pro users)
            const tabButton = document.getElementById('tab-' + tabToShow);
            if (tabButton) {
                switchTab(tabToShow, false); // Don't update URL on initial load
            } else {
                // If tab from URL doesn't exist, fall back to default
                switchTab(defaultTab, false);
            }
        });

        // Handle browser back/forward buttons
        window.addEventListener('popstate', function(event) {
            const urlParams = new URLSearchParams(window.location.search);
            const tabFromUrl = urlParams.get('tab') || 'displays';
            const tabButton = document.getElementById('tab-' + tabFromUrl);
            if (tabButton) {
                switchTab(tabFromUrl, false);
            }
        });

    </script>
@endpush

@push('modals')
    <x-modals.select-permission provider="outlook" />
    <x-modals.select-permission provider="google" />
    <x-modals.select-google-booking-method />
    <x-modals.google-service-account />
@endpush
