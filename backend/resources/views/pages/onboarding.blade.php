@extends('layouts.blank')
@section('title', 'Welcome, '.auth()->user()->name.'!')
@section('page')
@php
    $hasAccounts = $outlookAccounts->count() > 0 || $googleAccounts->count() > 0 || $caldavAccounts->count() > 0;
    $hasUsageType = auth()->user()->usage_type !== null;
    $currentStep = $hasUsageType ? ($hasAccounts ? 3 : 2) : 1;
@endphp

<div class="flex min-h-full flex-col justify-center py-24 sm:px-6 lg:px-8">
    <div class="sm:mx-auto sm:w-full sm:max-w-md">
        <div class="flex justify-center">
            <img class="h-12 w-auto mb-4" src="/images/logo-black.svg" alt="Logo">
        </div>
        <h2 class="mt-6 text-center text-2xl/9 font-bold tracking-tight text-gray-900">Let's get your calendars connected 🥳</h2>
        <p class="mt-2 text-center text-lg text-gray-500">We'll walk you through setting up a display in just a few minutes</p>
    </div>

    <x-cards.card class="mt-10 mx-auto w-full max-w-xl">
        <div class="p-4">
            {{-- Stepper --}}
            <div class="flex items-center justify-center pt-4 mb-12">
                <div class="flex items-center space-x-6">
                    <div class="flex flex-col items-center">
                        <div class="w-10 h-10 rounded-full {{ $currentStep === 1 ? 'bg-oxford text-white' : 'bg-gray-100 text-gray-400' }} flex items-center justify-center font-semibold text-lg">1</div>
                        <span class="mt-2 text-sm font-medium {{ $currentStep === 1 ? 'text-gray-900' : 'text-gray-500' }}">Usage</span>
                    </div>
                    <div class="w-16 h-0.5 bg-gray-300"></div>
                    <div class="flex flex-col items-center">
                        <div class="w-10 h-10 rounded-full {{ $currentStep === 2 ? 'bg-oxford text-white' : 'bg-gray-100 text-gray-400' }} flex items-center justify-center font-semibold text-lg">2</div>
                        <span class="mt-2 text-sm font-medium {{ $currentStep === 2 ? 'text-gray-900' : 'text-gray-500' }}">Account</span>
                    </div>
                    <div class="w-16 h-0.5 bg-gray-300"></div>
                    <div class="flex flex-col items-center">
                        <div class="w-10 h-10 rounded-full {{ $currentStep === 3 ? 'bg-oxford text-white' : 'bg-gray-100 text-gray-400' }} flex items-center justify-center font-semibold text-lg">3</div>
                        <span class="mt-2 text-sm font-medium {{ $currentStep === 3 ? 'text-gray-900' : 'text-gray-500' }}">Display</span>
                    </div>
                </div>
            </div>

            <x-alerts.alert />

            @if(!auth()->user()->usage_type)
                <div class="min-w-0 text-center">
                    <div class="flex items-center justify-center gap-x-3">
                        <p class="text-lg font-semibold leading-6 text-gray-900">How will you use Spacepad?</p>
                    </div>
                    <div class="mt-3 flex items-center justify-center gap-x-2 text-md leading-5 text-gray-500 max-w-md mx-auto">
                        <p class="break-words leading-6">This helps us understand our user base and provide appropriate features and pricing.</p>
                    </div>
                </div>
                <form action="{{ route('onboarding.usage-type') }}" method="POST" class="mt-8 space-y-4">
                    @csrf
                    <div class="grid grid-cols-1 gap-4">
                        <button type="submit" name="usage_type" value="business" class="flex items-center justify-center gap-3 rounded-lg border border-gray-200 bg-white p-4 shadow-sm hover:border-blue-500 hover:shadow-md transition-all duration-200">
                            <x-icons.building class="h-6 w-6" />
                            <span class="font-medium text-gray-900">Business</span>
                        </button>
                        <button type="submit" name="usage_type" value="personal" class="flex items-center justify-center gap-3 rounded-lg border border-gray-200 bg-white p-4 shadow-sm hover:border-blue-500 hover:shadow-md transition-all duration-200">
                            <x-icons.users class="h-6 w-6" />
                            <span class="font-medium text-gray-900">Personal / Community</span>
                        </button>
                    </div>
                </form>
            @elseif(!$hasAccounts)
                <div class="min-w-0 text-center">
                    <div class="flex items-center justify-center gap-x-3">
                        <p class="text-lg font-semibold leading-6 text-gray-900">Connect your first account</p>
                    </div>
                    <div class="mt-3 flex items-center justify-center gap-x-2 text-md leading-5 text-gray-500 max-w-md mx-auto">
                        <p class="break-words leading-6">You'll be able to connect multiple accounts from different providers and display events from the calendars and rooms of the account.</p>
                    </div>
                </div>
                <div class="flex flex-col gap-y-4 mt-8 w-full">
                    @if(config('services.microsoft.enabled'))
                        <a href="{{ route('outlook-accounts.auth') }}"
                           class="flex items-center justify-center gap-3 rounded-lg border border-gray-200 bg-white p-4 shadow-sm hover:border-blue-500 hover:shadow-md transition-all duration-200">
                            <x-icons.microsoft class="h-6 w-6" />
                            <span class="font-medium text-gray-900">Connect a Microsoft account</span>
                        </a>
                    @endif

                    @if(config('services.google.enabled'))
                        <a href="{{ route('google-accounts.auth') }}"
                           class="flex items-center justify-center gap-3 rounded-lg border border-gray-200 bg-white p-4 shadow-sm hover:border-blue-500 hover:shadow-md transition-all duration-200">
                            <x-icons.google class="h-6 w-6" />
                            <span class="font-medium text-gray-900">Connect a Google account</span>
                        </a>
                    @endif

                    @if(config('services.caldav.enabled'))
                        <a href="{{ route('caldav-accounts.create') }}"
                           class="flex items-center justify-center gap-3 rounded-lg border border-gray-200 bg-white p-4 shadow-sm hover:border-blue-500 hover:shadow-md transition-all duration-200">
                            <x-icons.calendar class="h-6 w-6 text-gray-600" />
                            <span class="font-medium text-gray-900">Connect to a CalDAV server</span>
                        </a>
                    @endif
                </div>
            @else
                <div class="min-w-0 text-center">
                    <div class="flex items-center justify-center gap-x-3">
                        <p class="text-lg font-semibold leading-6 text-gray-900">Set up your first display</p>
                    </div>
                    <div class="mt-3 flex items-center justify-center gap-x-2 text-md leading-5 text-gray-500 max-w-md mx-auto">
                        <p class="break-words leading-6">Create a display and pick the calendar or room you would like to synchronize. You are able to connect multiple tablets to one display.</p>
                    </div>
                </div>
                <div class="flex items-center justify-center gap-x-4 mt-6 py-4">
                    <a href="{{ route('displays.create') }}" class="inline-flex items-center rounded-md bg-white py-3 px-4 text-md font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                        Create a new display
                    </a>
                </div>
            @endif
        </div>
    </x-cards.card>
</div>
@endsection
