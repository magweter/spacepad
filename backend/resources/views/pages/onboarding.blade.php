@extends('layouts.blank')
@section('title', 'Welcome, '.auth()->user()->name.'!')
@section('page')
@php
    $hasAccounts = $outlookAccounts->count() > 0 || $googleAccounts->count() > 0;
    $currentStep = $hasAccounts ? 2 : 1;
@endphp

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
                        <div class="w-10 h-10 rounded-full {{ $currentStep === 1 ? 'bg-oxford text-white' : 'bg-gray-100 text-gray-400' }} flex items-center justify-center font-semibold text-lg">1</div>
                        <span class="mt-2 text-sm font-medium {{ $currentStep === 1 ? 'text-gray-900' : 'text-gray-500' }}">Account</span>
                    </div>
                    <div class="w-16 h-0.5 bg-gray-300"></div>
                    <div class="flex flex-col items-center">
                        <div class="w-10 h-10 rounded-full {{ $currentStep === 2 ? 'bg-oxford text-white' : 'bg-gray-100 text-gray-400' }} flex items-center justify-center font-semibold text-lg">2</div>
                        <span class="mt-2 text-sm font-medium {{ $currentStep === 2 ? 'text-gray-900' : 'text-gray-500' }}">Display</span>
                    </div>
                </div>
            </div>

            <x-alerts.alert />

            @if(!$hasAccounts)
                <div class="min-w-0 text-center">
                    <div class="flex items-center justify-center gap-x-3">
                        <p class="text-md font-semibold leading-6 text-gray-900">Connect your first account</p>
                    </div>
                    <div class="mt-3 flex items-center justify-center gap-x-2 text-md leading-5 text-gray-500">
                        <p class="break-words leading-6">You'll be able to connect multiple accounts from different providers and display events from the calendars and rooms of the account.</p>
                    </div>
                </div>
                <div class="flex items-center justify-center gap-x-4 mt-8">
                    <a href="{{ route('outlook-accounts.auth') }}" class="inline-flex items-center rounded-md bg-white py-3 px-4 text-md font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                        <x-icons.microsoft class="h-4 w-4 mr-2" />
                        <span>Connect an Outlook account</span>
                    </a>
                </div>
                <div class="flex items-center justify-center gap-x-4 mt-4">
                    <a href="{{ route('google-accounts.auth') }}" class="inline-flex items-center rounded-md bg-white py-3 px-4 text-md font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                        <x-icons.google class="h-4 w-4 mr-2" />
                        <span>Connect a Google account</span>
                    </a>
                </div>
            @else
                <div class="min-w-0 text-center">
                    <div class="flex items-center justify-center gap-x-3">
                        <p class="text-md font-semibold leading-6 text-gray-900">Set up your first display</p>
                    </div>
                    <div class="mt-3 flex items-center justify-center gap-x-2 text-md leading-5 text-gray-500">
                        <p class="break-words leading-6">Create a display and pick the calendar or room you would like to synchronize. You are able to connect multiple tablets to one display.</p>
                    </div>
                </div>
                <div class="flex items-center justify-center gap-x-4 mt-6">
                    <a href="{{ route('displays.create') }}" class="inline-flex items-center rounded-md bg-white py-3 px-4 text-md font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                        Create a new display
                    </a>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
