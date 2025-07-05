@extends('layouts.base')
@section('title', 'Create a new CalDAV Account')
@section('container_class', 'max-w-5xl')
@section('content')
    <x-cards.card>
        {{-- Session Status Alert --}}
        <x-alerts.alert />

        <div>
            <h1 class="text-base font-semibold leading-6 text-gray-900">Enter your credentials</h1>
            <p class="mt-2 text-sm text-gray-700">We'll connect to your server to access your calendars.</p>
        </div>

        <form action="{{ route('caldav-accounts.store') }}" method="POST" class="mt-6">
            @csrf
            <div class="space-y-6">
                <div>
                    <label for="url" class="block text-sm font-medium leading-6 text-gray-900">Server URL</label>
                    <div class="mt-2">
                        <input type="url" name="url" id="url" value="{{ old('url') }}"
                               class="block w-full rounded-md border-0 py-1.5 px-3 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-blue-600 sm:text-sm sm:leading-6"
                               placeholder="https://example.com/remote.php/dav">
                    </div>
                    <p class="mt-2 text-sm text-gray-500">The URL of your CalDAV server.</p>
                </div>

                <div>
                    <label for="username" class="block text-sm font-medium leading-6 text-gray-900">Username</label>
                    <div class="mt-2">
                        <input type="text" name="username" id="username" value="{{ old('username') }}"
                               class="block w-full rounded-md border-0 py-1.5 px-3 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-blue-600 sm:text-sm sm:leading-6">
                    </div>
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium leading-6 text-gray-900">Password</label>
                    <div class="mt-2">
                        <input type="password" name="password" id="password"
                               class="block w-full rounded-md border-0 py-1.5 px-3 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-blue-600 sm:text-sm sm:leading-6">
                    </div>
                </div>

                <div class="flex items-center justify-end gap-x-6">
                    <a href="{{ route('dashboard') }}" class="text-sm font-semibold leading-6 text-gray-900">Cancel</a>
                    <button type="submit"
                            class="rounded-md bg-blue-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600">
                        Connect Account
                    </button>
                </div>
            </div>
        </form>
    </x-cards.card>
@endsection
