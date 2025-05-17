@extends('layouts.base')
@section('title', 'Create a new display')
@section('content')
    <!-- Session Status Alert -->
    @if(session('status'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4">
            {{ session('status') }}
        </div>
    @endif

    <!-- Validation Errors Alert -->
    @if ($errors->any())
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4">
            <strong>Please pay attention to the following errors.</strong>
            <ul class="mt-2 list-disc list-inside">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form id="createForm" action="{{ route('displays.store') }}" method="POST">
        @csrf
        <input type="hidden" name="provider" id="providerInput" value="">
        <div class="flex flex-col">
            <div class="flow-root">
                <div class="mb-4">
                    <label for="name" class="block text-sm font-medium leading-6 text-gray-900">Device name</label>
                    <div class="mt-2">
                        <input type="text" name="name" id="name" value="{{ old('name') }}"
                               class="block w-full rounded-md border-0 py-1.5 px-3 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-blue-600 sm:text-sm sm:leading-6"
                               placeholder="">
                    </div>
                    <p class="mt-2 text-sm text-gray-500">This name is only used in the dashboard and for your identification only.</p>
                </div>
                <div class="mb-4">
                    <label for="displayName" class="block text-sm font-medium leading-6 text-gray-900">Room name</label>
                    <div class="mt-2">
                        <input type="text" name="displayName" id="displayName" value="{{ old('displayName') }}"
                               class="block w-full rounded-md border-0 py-1.5 px-3 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-blue-600 sm:text-sm sm:leading-6"
                               placeholder="">
                    </div>
                    <p class="mt-2 text-sm text-gray-500">This name will be displayed on the top right corner of the screen.</p>
                </div>

                <!-- Step 1: Provider Selection -->
                <div class="mt-6" id="providerSelection">
                    <p class="text-sm font-semibold leading-6 text-gray-900">Select a calendar account</p>
                    <p class="mt-1 text-sm leading-6 text-gray-600">Choose the service you want to connect to.</p>
                    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div class="relative flex items-center space-x-3 rounded-lg border border-gray-300 {{ count($outlookAccounts) > 0 && config('services.microsoft.enabled') ? 'bg-white hover:border-gray-400 cursor-pointer' : 'bg-gray-50 opacity-75 cursor-not-allowed' }} px-6 py-5 shadow-sm focus-within:ring-2 focus-within:ring-blue-500 focus-within:ring-offset-2 provider-tile" data-provider="outlook">
                            <div class="flex-shrink-0">
                                <x-icons.microsoft class="h-10 w-10" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <span class="absolute inset-0" aria-hidden="true"></span>
                                <p class="text-sm font-medium text-gray-900">Microsoft 365</p>
                                <p class="truncate text-sm text-gray-500">
                                    @if(count($outlookAccounts) > 0)
                                        Connect to Outlook calendars
                                    @else
                                        Connect an account first, via the dashboard
                                    @endif
                                </p>
                            </div>
                        </div>
                        <div class="relative flex items-center space-x-3 rounded-lg border border-gray-300 {{ count($googleAccounts) > 0 && config('services.google.enabled') ? 'bg-white hover:border-gray-400 cursor-pointer' : 'bg-gray-50 opacity-75 cursor-not-allowed' }} px-6 py-5 shadow-sm focus-within:ring-2 focus-within:ring-blue-500 focus-within:ring-offset-2 provider-tile" data-provider="google">
                            <div class="flex-shrink-0">
                                <x-icons.google class="h-10 w-10" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <span class="absolute inset-0" aria-hidden="true"></span>
                                <p class="text-sm font-medium text-gray-900">Google Calendar</p>
                                <p class="truncate text-sm text-gray-500">
                                    @if(count($googleAccounts) > 0)
                                        Connect to Google calendars
                                    @else
                                        Connect an account first, via the dashboard
                                    @endif
                                </p>
                            </div>
                        </div>
                        <div class="relative flex items-center space-x-3 rounded-lg border border-gray-300 bg-gray-50 px-6 py-5 opacity-75 cursor-not-allowed" data-provider="caldav">
                            <div class="flex-shrink-0">
                                <x-icons.caldav class="h-10 w-10" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <span class="absolute inset-0" aria-hidden="true"></span>
                                <p class="text-sm font-medium text-gray-900">CalDAV</p>
                                <p class="truncate text-sm text-gray-500">Coming soon</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 2: Search Method Selection (initially hidden) -->
                <div class="mt-6 hidden" id="searchMethodSelection">
                    <p class="text-sm font-semibold leading-6 text-gray-900">How do you want to find your calendar?</p>
                    <p class="mt-1 text-sm leading-6 text-gray-600">Choose how you want to search for your calendar.</p>
                    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div class="relative flex items-center space-x-3 rounded-lg border border-gray-300 bg-white px-6 py-5 shadow-sm focus-within:ring-2 focus-within:ring-blue-500 focus-within:ring-offset-2 hover:border-gray-400 cursor-pointer search-method-tile" data-method="calendar">
                            <div class="min-w-0 flex-1">
                                <span class="absolute inset-0" aria-hidden="true"></span>
                                <p class="text-sm font-medium text-gray-900">Search by Calendar</p>
                                <p class="truncate text-sm text-gray-500">Find a specific calendar</p>
                            </div>
                        </div>
                        <div class="relative flex items-center space-x-3 rounded-lg border border-gray-300 bg-white px-6 py-5 shadow-sm focus-within:ring-2 focus-within:ring-blue-500 focus-within:ring-offset-2 hover:border-gray-400 cursor-pointer search-method-tile" data-method="room">
                            <div class="min-w-0 flex-1">
                                <span class="absolute inset-0" aria-hidden="true"></span>
                                <p class="text-sm font-medium text-gray-900">Search by Room</p>
                                <p class="truncate text-sm text-gray-500">Find a room's calendar</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 3: Calendar/Room Selection (initially hidden) -->
                <div class="mt-6 hidden" id="calendarSelection">
                    <div id="outlookSelection" class="hidden">
                        <p class="text-sm font-semibold leading-6 text-gray-900">Outlook Account</p>
                        <p class="mt-1 text-sm leading-6 text-gray-600">Pick the account and the desired calendar or room to display.</p>
                        <div class="mt-4 space-y-4">
                            @foreach($outlookAccounts as $outlookAccount)
                                <div class="flex items-start">
                                    <div class="flex items-center p-1 mr-2">
                                        <input
                                            id="{{ $outlookAccount->id }}"
                                            name="account"
                                            value="{{ $outlookAccount->id }}"
                                            type="radio"
                                            class="h-4 w-4 border-gray-300 text-blue-600 focus:ring-blue-600"
                                            data-calendar-url="{{ route('calendars.outlook', $outlookAccount->id) }}"
                                            data-room-url="{{ route('rooms.outlook', $outlookAccount->id) }}"
                                            hx-target="#pickResource"
                                            hx-swap="innerHTML"
                                        >
                                    </div>
                                    <div class="flex items-center p-1 mr-2">
                                        <x-icons.microsoft class="size-4 text-muted-foreground inline-flex"/>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <label for="account" class="font-medium text-gray-900 text-sm">{{ $outlookAccount->name }}</label>
                                        <p class="text-gray-500 text-sm">{{ $outlookAccount->email }}</p>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    <div id="googleSelection" class="hidden">
                        <p class="text-sm font-semibold leading-6 text-gray-900">Google Account</p>
                        <p class="mt-1 text-sm leading-6 text-gray-600">Pick the account and the desired calendar or room to display.</p>
                        <div class="mt-4 space-y-4">
                            @foreach($googleAccounts as $googleAccount)
                                <div class="flex items-start">
                                    <div class="flex items-center p-1 mr-2">
                                        <input
                                            id="{{ $googleAccount->id }}"
                                            name="account"
                                            value="{{ $googleAccount->id }}"
                                            type="radio"
                                            class="h-4 w-4 border-gray-300 text-blue-600 focus:ring-blue-600"
                                            data-calendar-url="{{ route('calendars.google', $googleAccount->id) }}"
                                            data-room-url="{{ route('rooms.google', $googleAccount->id) }}"
                                            hx-target="#pickResource"
                                            hx-swap="innerHTML"
                                        >
                                    </div>
                                    <div class="flex items-center p-1 mr-2">
                                        <x-icons.google class="size-4 text-muted-foreground inline-flex"/>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <label for="account" class="font-medium text-gray-900 text-sm">{{ $googleAccount->name }}</label>
                                        <p class="text-gray-500 text-sm">{{ $googleAccount->email }}</p>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    <div id="caldavSelection" class="hidden">
                        <!-- CalDAV selection will be added here -->
                        <p class="text-sm text-gray-500">CalDAV integration coming soon.</p>
                    </div>

                    <div id="pickResource" class="mt-4"></div>
                </div>
            </div>

            <button type="submit" id="submitButton"
                    class="relative ms-auto mt-6 block rounded-md bg-green-600 disabled:bg-gray-300 disabled:cursor-not-allowed px-3 py-2 text-center text-sm font-semibold text-white hover:bg-green-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-green-600" disabled>
                <span id="buttonText">Continue and create display</span>
            </button>
        </div>
    </form>
@endsection

@push('scripts')
    <script>
        const submitButton = document.getElementById('submitButton');
        const buttonText = document.getElementById('buttonText');
        const form = document.getElementById('createForm');
        const providerInput = document.getElementById('providerInput');
        const providerSelection = document.getElementById('providerSelection');
        const searchMethodSelection = document.getElementById('searchMethodSelection');
        const calendarSelection = document.getElementById('calendarSelection');
        const outlookSelection = document.getElementById('outlookSelection');
        const googleSelection = document.getElementById('googleSelection');
        const caldavSelection = document.getElementById('caldavSelection');
        const pickResource = document.getElementById('pickResource');

        // Function to check if a calendar or room is selected
        function checkSelection() {
            const selectedCalendar = pickResource.querySelector('input[name="calendar"]:checked');
            const selectedRoom = pickResource.querySelector('input[name="room"]:checked');
            const selectedCalendarSelect = pickResource.querySelector('select[name="calendar"]');
            const selectedRoomSelect = pickResource.querySelector('select[name="room"]');
            
            const hasCalendarSelection = selectedCalendar || (selectedCalendarSelect && selectedCalendarSelect.value !== '');
            const hasRoomSelection = selectedRoom || (selectedRoomSelect && selectedRoomSelect.value !== '');
            
            submitButton.disabled = !(hasCalendarSelection || hasRoomSelection);
        }

        // Listen for changes in the pickResource div
        const observer = new MutationObserver(checkSelection);
        observer.observe(pickResource, { 
            childList: true, 
            subtree: true 
        });

        // Also listen for changes in select elements
        document.addEventListener('change', function(e) {
            if (e.target.matches('select[name="calendar"], select[name="room"]')) {
                checkSelection();
            }
        });

        // Provider selection
        document.querySelectorAll('.provider-tile').forEach(tile => {
            tile.addEventListener('click', function() {
                // Only allow click if the tile is not disabled
                if (this.classList.contains('cursor-not-allowed')) {
                    return;
                }

                // Remove selected state from all tiles
                document.querySelectorAll('.provider-tile').forEach(t => {
                    t.classList.remove('ring-2', 'ring-blue-500', 'ring-offset-2');
                });
                // Add selected state to clicked tile
                this.classList.add('ring-2', 'ring-blue-500', 'ring-offset-2');
                
                // Set the provider value
                providerInput.value = this.dataset.provider;
                
                // Show search method selection
                searchMethodSelection.classList.remove('hidden');
                
                // Hide calendar selection initially
                calendarSelection.classList.add('hidden');

                // Remove selected state from all tiles
                document.querySelectorAll('.search-method-tile').forEach(t => {
                    t.classList.remove('ring-2', 'ring-blue-500', 'ring-offset-2');
                });

                // Disable submit button when changing provider
                submitButton.disabled = true;
            });
        });

        // Search method selection
        document.querySelectorAll('.search-method-tile').forEach(tile => {
            tile.addEventListener('click', function() {
                // Remove selected state from all tiles
                document.querySelectorAll('.search-method-tile').forEach(t => {
                    t.classList.remove('ring-2', 'ring-blue-500', 'ring-offset-2');
                });
                // Add selected state to clicked tile
                this.classList.add('ring-2', 'ring-blue-500', 'ring-offset-2');
                
                // Clear the pickResource div
                pickResource.innerHTML = '';
                
                // Show calendar selection
                calendarSelection.classList.remove('hidden');
                
                // Show the appropriate provider selection
                const selectedProvider = document.querySelector('.provider-tile.ring-2').dataset.provider;
                const selectedMethod = this.dataset.method;
                
                // Update all radio buttons' hx-get URLs based on the selected method
                document.querySelectorAll('input[name="account"]').forEach(radio => {
                    radio.setAttribute('hx-get', selectedMethod === 'calendar' 
                        ? radio.dataset.calendarUrl 
                        : radio.dataset.roomUrl
                    );
                });
                
                htmx.process(document.body);
                
                outlookSelection.classList.add('hidden');
                googleSelection.classList.add('hidden');
                caldavSelection.classList.add('hidden');

                // Uncheck all radio buttons
                document.querySelectorAll('input[name="account"]').forEach(radio => {
                    radio.checked = false;
                });
                
                switch(selectedProvider) {
                    case 'outlook':
                        outlookSelection.classList.remove('hidden');
                        break;
                    case 'google':
                        googleSelection.classList.remove('hidden');
                        break;
                    case 'caldav':
                        caldavSelection.classList.remove('hidden');
                        break;
                }

                // Disable submit button when changing search method
                submitButton.disabled = true;
            });
        });

        submitButton.addEventListener('click', function () {
            // Show the spinner and hide the button text
            buttonText.innerText = 'Creating...';

            // Optionally, disable the button to prevent multiple submissions
            submitButton.disabled = true;

            // Submit the form
            form.submit();
        });
    </script>
@endpush
