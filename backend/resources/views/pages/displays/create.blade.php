@extends('layouts.base')
@section('title', 'Welcome, '.auth()->user()->name.'!')
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
        <div class="flex flex-col">
            <div class="sm:flex sm:items-center">
                <div class="sm:flex-auto">
                    <h1 class="text-xl font-semibold leading-6 text-gray-900">New display</h1>
                    <p class="mt-2 text-sm text-gray-700">Create a new display.</p>
                </div>
            </div>
            <div class="mt-6 flow-root">
                <div class="mb-4">
                    <label for="name" class="block text-sm font-medium leading-6 text-gray-900">Device name</label>
                    <div class="mt-2">
                        <input type="text" name="name" id="name" value="{{ old('name') }}"
                               class="block w-full rounded-md border-0 py-1.5 px-3 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-blue-600 sm:text-sm sm:leading-6"
                               placeholder="">
                    </div>
                    <p class="mt-2 text-sm text-gray-500">This name is for your reference only.</p>
                </div>
                <div class="mb-4">
                    <label for="displayName" class="block text-sm font-medium leading-6 text-gray-900">Display name</label>
                    <div class="mt-2">
                        <input type="text" name="displayName" id="displayName" value="{{ old('displayName') }}"
                               class="block w-full rounded-md border-0 py-1.5 px-3 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-blue-600 sm:text-sm sm:leading-6"
                               placeholder="">
                    </div>
                    <p class="mt-2 text-sm text-gray-500">This name will be displayed on the screen.</p>
                </div>
                <div class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="rounded-lg border relative flex flex-col justify-between">
                        <div class="p-6">
                            <p class="text-sm font-semibold leading-6 text-gray-900">Outlook Account</p>
                            <p class="mt-1 text-sm leading-6 text-gray-600">Pick the account and the desired room to display.</p>
                            <div class="mt-2.5 divide-y divide-gray-200">
                                @foreach($outlookAccounts as $outlookAccount)
                                    <div class="relative flex items-start pb-4 pt-3.5">
                                        <div class="flex items-center p-1 mr-2">
                                            <x-icons.microsoft class="size-4 text-muted-foreground inline-flex"/>
                                        </div>
                                        <div class="min-w-0 flex-1 text-sm leading-6">
                                            <label for="account" class="font-medium text-gray-900">{{ $outlookAccount->name }}</label>
                                            <p class="text-gray-500">{{ $outlookAccount->email }}</p>
                                        </div>
                                        <div class="ml-3 flex h-6 items-center">
                                            <input
                                                id="{{ $outlookAccount->id }}"
                                                name="account"
                                                value="{{ $outlookAccount->id }}"
                                                type="radio" {{ $loop->first ? 'checked' : '' }}
                                                class="h-4 w-4 border-gray-300 text-blue-600 focus:ring-blue-600"
                                                hx-get="{{ route('rooms.outlook', $outlookAccount->id) }}"
                                                hx-target="#room"
                                                hx-swap="innerHTML"
                                            >
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                        <div id="room" class="bg-gray-100 p-6 pt-4 border-b-lg">
                            @include('components.rooms.outlook', ['rooms' => $outlookAccounts->first()->getRooms()])
                        </div>
                    </div>
                </div>
            </div>

            <button type="submit" id="submitButton"
                    class="relative ms-auto mt-6 block rounded-md bg-green-600 disabled:bg-green-700 px-3 py-2 text-center text-sm font-semibold text-white shadow-sm hover:bg-green-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-green-600">
                <span id="buttonText">Continue and sync calendars</span>
            </button>
        </div>
    </form>
@endsection

@push('scripts')
    <script>
        const submitButton = document.getElementById('submitButton');
        const buttonText = document.getElementById('buttonText');
        const form = document.getElementById('createForm');

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
