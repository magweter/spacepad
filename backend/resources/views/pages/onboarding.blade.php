@extends('layouts.blank')
@section('title', 'Welcome, ' . auth()->user()->name . '!')
@section('page')

    <div class="relative">
        <div class="absolute top-4 right-4">
            <form action="{{ route('logout') }}" method="POST">
                @csrf
                <button type="submit"
                    class="inline-flex items-center gap-x-2 rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                    <x-icons.logout class="h-5 w-5 text-gray-400" />
                    Logout
                </button>
            </form>
        </div>

        <div class="flex min-h-full flex-col justify-center py-24 sm:px-6 lg:px-8">
            <div class="sm:mx-auto sm:w-full sm:max-w-md">
                <div class="flex justify-center">
                    <img class="h-12 w-auto mb-4" src="/images/logo-black.svg" alt="Logo">
                </div>
                <h2 class="mt-6 text-center text-2xl/9 font-bold tracking-tight text-gray-900">Let's get you set up! 🥳</h2>
                <p class="mt-2 text-center text-lg text-gray-500">We'll walk you through setting up a display<br> in just a
                    few minutes</p>
            </div>

            <x-cards.card class="mt-10 mx-auto w-full max-w-xl">
                <div class="p-8">
                    <x-alerts.alert />

                    @if(!$hasUsageType)
                        <div class="min-w-0 text-center">
                            <div class="flex items-center justify-center gap-x-3">
                                <p class="text-lg font-semibold leading-6 text-gray-900">How will you use Spacepad?</p>
                            </div>
                            <div
                                class="mt-3 flex items-center justify-center gap-x-2 text-md leading-5 text-gray-500 max-w-md mx-auto">
                                <p class="break-words leading-6">This helps us understand our user base and provide appropriate
                                    features and pricing.</p>
                            </div>
                        </div>
                        <form action="{{ route('onboarding.usage-type') }}" method="POST" class="mt-8 space-y-4">
                            @csrf
                            <div class="grid grid-cols-1 gap-4">
                                <button type="submit" name="usage_type" value="business"
                                    class="flex items-center justify-center gap-3 rounded-lg border border-gray-200 bg-white p-4 shadow-sm hover:border-blue-500 hover:shadow-md transition-all duration-200 cursor-pointer">
                                    <x-icons.building class="h-6 w-6" />
                                    <span class="font-medium text-gray-900">I am using this for a business or
                                        organization</span>
                                </button>
                                <button type="submit" name="usage_type" value="personal"
                                    class="flex items-center justify-center gap-3 rounded-lg border border-gray-200 bg-white p-4 shadow-sm hover:border-blue-500 hover:shadow-md transition-all duration-200 cursor-pointer">
                                    <x-icons.users class="h-6 w-6" />
                                    <span class="font-medium text-gray-900">I am a hobbyist / personal user</span>
                                </button>
                            </div>
                        </form>
                    @elseif(!$hasAcceptedTerms)
                        <div class="min-w-0 text-center">
                            <div class="flex items-center justify-center gap-x-3">
                                <p class="text-lg font-semibold leading-6 text-gray-900">Self-hosted License Agreement</p>
                            </div>
                            <div
                                class="mt-3 flex flex-col items-center justify-center gap-x-2 text-md leading-5 text-gray-500 max-w-md mx-auto">
                                <p class="break-words leading-6 mb-3">
                                    In order to be the perfect all-encompassing room display solution for SMB's it is mandatory
                                    Spacepad is sustainable.
                                </p>
                                <p class="break-words leading-6">
                                    That's why as a self-hosted user, we need your agreement on two important points:
                                </p>
                            </div>
                            <div class="mt-6 text-left space-y-4">
                                <div class="bg-gray-50 p-4 rounded-lg">
                                    <h4 class="font-medium text-gray-900 mb-2">1. Fair Use</h4>
                                    <p class="text-gray-600">We collect your email address to verify licensing and ensure fair
                                        use. This is not shared externally and is only used for administrative purposes.</p>
                                </div>
                                <div class="bg-gray-50 p-4 rounded-lg">
                                    <h4 class="font-medium text-gray-900 mb-2">2. License Agreement</h4>
                                    <p class="text-gray-600">By using Spacepad, you agree to our <a
                                            class="text-blue-500 underline"
                                            href="https://github.com/magweter/spacepad?tab=readme-ov-file#license"
                                            target="_blank">licensing terms</a>. Personal users get full access, while business
                                        users need a valid license key for multiple displays and Pro features.</p>
                                </div>
                            </div>
                        </div>
                        <form action="{{ route('onboarding.terms') }}" method="POST" class="mt-8">
                            @csrf
                            <div class="flex items-center justify-center">
                                <button type="submit"
                                    class="inline-flex items-center rounded-md bg-oxford px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600">
                                    I understand and agree
                                </button>
                            </div>
                        </form>
                    @elseif(!$hasAnyAccount)
                        <div class="min-w-0 text-center">
                            <div class="flex items-center justify-center gap-x-3">
                                <p class="text-lg font-semibold leading-6 text-gray-900">Connect your first account</p>
                            </div>
                            <div
                                class="mt-3 flex items-center justify-center gap-x-2 text-md leading-5 text-gray-500 max-w-md mx-auto">
                                <p class="break-words leading-6">You'll be able to connect multiple accounts from different
                                    providers and display events from the calendars and rooms of the account.</p>
                            </div>
                        </div>
                        <div class="flex flex-col gap-y-4 mt-8 w-full">
                            @if(config('services.microsoft.enabled'))
                                <button type="button"
                                    onclick="window.dispatchEvent(new CustomEvent('open-permission-modal', { detail: { provider: 'outlook' } }))"
                                    class="flex items-center justify-center gap-3 rounded-lg border border-gray-200 bg-white p-4 shadow-sm hover:border-blue-500 hover:shadow-md transition-all duration-200">
                                    <x-icons.microsoft class="h-6 w-6" />
                                    <span class="font-medium text-gray-900">Connect a Microsoft account</span>
                                </button>
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
                    @endif
                </div>
            </x-cards.card>
        </div>
    </div>
@endsection

@push('modals')
    <x-modals.select-permission provider="outlook" />
    <x-modals.select-permission provider="google" />
@endpush