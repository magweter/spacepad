@extends('layouts.blank')
@section('title', 'Welcome, '.auth()->user()->name.'!')
@section('page')
<div class="flex min-h-full flex-col justify-center py-24 sm:px-6 lg:px-8">
    <div class="sm:mx-auto sm:w-full sm:max-w-md">
        <div class="flex justify-center">
            <img class="h-12 w-auto mb-4" src="/images/logo-black.svg" alt="Logo">
        </div>
        <h2 class="mt-6 text-center text-2xl/9 font-bold tracking-tight text-gray-900">Let's get your rooms connected 🥳</h2>
        <p class="mt-2 text-center text-lg text-gray-500">We'll walk you through setting up a device in just a few minutes</p>
    </div>

    <div class="mt-10 sm:mx-auto sm:w-full sm:max-w-[480px]">
        <div class="bg-white px-6 py-12 border sm:rounded-lg sm:px-12 shadow">
            {{-- Stepper --}}
            <div class="flex items-center justify-center mb-8">
                <div class="flex items-center space-x-6">
                    <div class="flex flex-col items-center">
                        <div class="w-10 h-10 rounded-full {{ $outlookAccounts->count() > 0 ? 'bg-gray-100 text-gray-400' : 'bg-oxford text-white' }} flex items-center justify-center font-semibold text-lg">1</div>
                        <span class="mt-2 text-sm font-medium {{ $outlookAccounts->count() > 0 ? 'text-gray-500' : 'text-gray-900' }}">Account</span>
                    </div>
                    <div class="w-16 h-0.5 bg-gray-300"></div>
                    <div class="flex flex-col items-center">
                        <div class="w-10 h-10 rounded-full {{ $outlookAccounts->count() > 0 ? 'bg-oxford text-white' : 'bg-gray-100 text-gray-400' }} flex items-center justify-center font-semibold text-lg">2</div>
                        <span class="mt-2 text-sm font-medium {{ $outlookAccounts->count() > 0 ? 'text-gray-900' : 'text-gray-500' }}">Display</span>
                    </div>
                </div>
            </div>

            @if(session('status'))
                <div id="alert" class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4">
                    {{ session('status') }}
                </div>
            @endif

            @if($outlookAccounts->count() <= 0)
                <div class="min-w-0 text-center">
                    <div class="flex items-center justify-center gap-x-3">
                        <p class="text-md font-semibold leading-6 text-gray-900">Connect an Outlook or Microsoft 365 account</p>
                    </div>
                    <div class="mt-4 flex items-center justify-center gap-x-2 text-md leading-5 text-gray-500">
                        <p class="break-words">You'll be able to show rooms from this account on a display.</p>
                    </div>
                </div>
                <div class="flex items-center justify-center gap-x-4 mt-6">
                    <a href="{{ route('outlook-accounts.auth') }}" class="block rounded-md bg-white px-2.5 py-1.5 text-md font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                        Connect an Outlook account
                    </a>
                </div>
            @endif
                
            @if($outlookAccounts->count() > 0)
                <div class="min-w-0 text-center">
                    <div class="flex items-center justify-center gap-x-3">
                        <p class="text-md font-semibold leading-6 text-gray-900">Set up your first display</p>
                    </div>
                    <div class="mt-4 flex items-center justify-center gap-x-2 text-md leading-5 text-gray-500">
                        <p class="break-words">Create a display and pick the calendar you would like to synchronize. You are able to connect multiple tablets to one display.</p>
                    </div>
                </div>
                <div class="flex items-center justify-center gap-x-4 mt-6">
                    <a href="{{ route('displays.create') }}" class="block rounded-md bg-white px-2.5 py-1.5 text-md font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                        Create a new display
                    </a>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
