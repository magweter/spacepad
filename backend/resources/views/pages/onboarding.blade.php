@extends('layouts.base')
@section('title', 'Welcome, '.auth()->user()->name.'!')
@section('content')
    <!-- Session Status Alert -->
    @if(session('status'))
        <div id="alert" class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4">
            {{ session('status') }}
        </div>
    @endif

    <div class="mb-4">
        <div class="sm:flex sm:items-center">
            <div class="sm:flex-auto">
                <h1 class="text-xl font-semibold leading-6 text-gray-900">Let's get your rooms connected 🥳</h1>
                <p class="mt-2 text-md text-gray-500">We'll walk you through setting up a device in just a few minutes.</p>
            </div>
        </div>
    </div>
    <ul role="list" class="divide-y divide-gray-100">
        <li class="flex items-center gap-x-4 py-5">
            <div class="min-w-0 flex items-center justify-center text-xl w-10">
                1.
            </div>
            <div class="min-w-0">
                <div class="flex items-start gap-x-3">
                    <p class="text-md font-semibold leading-6 text-gray-900">Connect an Outlook or Microsoft 365 account</p>
                </div>
                <div class="mt-1 flex items-center gap-x-2 text-md leading-5 text-gray-500">
                    <p class="whitespace-nowrap">You'll be able to use this account as a source</p>
                </div>
            </div>
            <div class="flex flex-none items-center gap-x-4 ml-auto">
                @if($outlookAccounts->count() <= 0)
                    <a href="{{ route('outlook-accounts.auth') }}" class="hidden rounded-md bg-white px-2.5 py-1.5 text-md font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 sm:block">
                        Connect an Outlook account
                    </a>
                @else
                    <button disabled class="hidden rounded-md bg-green-50 px-2.5 py-1.5 text-md font-semibold text-green-700 shadow-sm ring-1 ring-inset ring-green-600/20 sm:block">
                        Completed
                    </button>
                @endif
            </div>
        </li>
        <li class="flex items-center gap-x-4 py-5">
            <div class="min-w-0 flex items-center justify-center text-xl w-10">
                2.
            </div>
            <div class="min-w-0">
                <div class="flex items-start gap-x-3">
                    <p class="text-md font-semibold leading-6 text-gray-900">Set up your first display</p>
                </div>
                <div class="mt-1 flex items-center gap-x-2 text-md leading-5 text-gray-500">
                    <p class="whitespace-nowrap">Create a displays and pick the calendars you would like to synchronize!</p>
                </div>
            </div>
            <div class="flex flex-none items-center gap-x-4 ml-auto">
                @if($outlookAccounts->count() > 0)
                    <a href="{{ route('displays.create') }}" class="hidden rounded-md bg-white px-2.5 py-1.5 text-md font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 sm:block">
                        Set up a display
                    </a>
                @else
                    <button disabled class="hidden rounded-md bg-white px-2.5 py-1.5 text-md font-semibold text-gray-500 shadow-sm ring-1 ring-inset ring-gray-100 sm:block">
                        Set up a display
                    </button>
                @endif
            </div>
        </li>
    </ul>
@endsection
