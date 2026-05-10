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
                    @php
                        $isSelfHosted = config('settings.is_self_hosted');
                        $totalSteps = $isSelfHosted ? 3 : 2;
                        if (!$hasUsageType) {
                            $currentStep = 1;
                        } elseif ($isSelfHosted && !$hasAcceptedTerms) {
                            $currentStep = 2;
                        } else {
                            $currentStep = $isSelfHosted ? 3 : 2;
                        }
                    @endphp
                    <div class="flex items-center justify-center gap-2 mb-6">
                        @for($i = 1; $i <= $totalSteps; $i++)
                            <div class="flex items-center gap-2">
                                @if($i < $currentStep)
                                    <span class="flex h-6 w-6 items-center justify-center rounded-full bg-blue-600 text-xs font-semibold text-white">
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                                    </span>
                                @elseif($i === $currentStep)
                                    <span class="flex h-6 w-6 items-center justify-center rounded-full bg-blue-600 text-xs font-semibold text-white">{{ $i }}</span>
                                @else
                                    <span class="flex h-6 w-6 items-center justify-center rounded-full bg-gray-200 text-xs font-semibold text-gray-500">{{ $i }}</span>
                                @endif
                                @if($i < $totalSteps)
                                    <div class="h-px w-8 {{ $i < $currentStep ? 'bg-blue-600' : 'bg-gray-200' }}"></div>
                                @endif
                            </div>
                        @endfor
                    </div>

                    <x-alerts.alert />

                    @if(!$hasUsageType)
                        <div class="min-w-0 text-center">
                            <div class="flex items-center justify-center gap-x-3">
                                <p class="text-lg font-semibold leading-6 text-gray-900">How will you use Spacepad?</p>
                            </div>
                            <div
                                class="mt-3 flex items-center justify-center gap-x-2 text-md leading-5 text-gray-500 max-w-md mx-auto">
                                <p class="break-words leading-6">This helps us understand our user base and provide appropriate features.</p>
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
                            <p class="text-lg font-semibold leading-6 text-gray-900">Connect your calendar</p>
                            <p class="mt-2 text-sm text-gray-500 leading-relaxed max-w-sm mx-auto">
                                Spacepad syncs your room calendars to show live availability on your display. Your data is never shared or sold.
                            </p>
                        </div>

                        {{-- Display preview --}}
                        <div class="mt-6 rounded-xl border border-gray-200 bg-gray-50 p-4 flex items-center gap-4">
                            <div class="shrink-0 w-2 self-stretch rounded-full bg-green-400"></div>
                            <div class="flex-1 min-w-0">
                                <p class="text-xs font-semibold uppercase tracking-wide text-gray-400 mb-1">Preview — Conference Room A</p>
                                <p class="text-lg font-bold text-gray-900">Available</p>
                                <p class="text-sm text-gray-500">Next: Team standup at 14:00</p>
                            </div>
                            <div class="shrink-0 text-right">
                                <p class="text-2xl font-bold text-gray-900 tabular-nums">{{ now()->format('H:i') }}</p>
                                <p class="text-xs text-gray-400">{{ now()->format('D d M') }}</p>
                            </div>
                        </div>

                        <div class="flex flex-col gap-y-3 mt-6 w-full">
                            @if(config('services.microsoft.enabled'))
                                <button type="button"
                                    onclick="window.dispatchEvent(new CustomEvent('open-permission-modal', { detail: { provider: 'outlook' } }))"
                                    class="flex items-center justify-center gap-3 rounded-lg border border-gray-200 bg-white p-4 shadow-sm hover:border-blue-500 hover:shadow-md transition-all duration-200">
                                    <x-icons.microsoft class="h-5 w-5" />
                                    <span class="font-medium text-gray-900">Continue with Microsoft 365</span>
                                </button>
                            @endif

                            @if(config('services.google.enabled'))
                                <button type="button"
                                    onclick="window.dispatchEvent(new CustomEvent('open-permission-modal', { detail: { provider: 'google' } }))"
                                    class="flex items-center justify-center gap-3 rounded-lg border border-gray-200 bg-white p-4 shadow-sm hover:border-blue-500 hover:shadow-md transition-all duration-200">
                                    <x-icons.google class="h-5 w-5" />
                                    <span class="font-medium text-gray-900">Continue with Google Workspace</span>
                                </button>
                            @endif

                            @if(config('services.caldav.enabled'))
                                <a href="{{ route('caldav-accounts.create') }}"
                                    class="flex items-center justify-center gap-3 rounded-lg border border-gray-200 bg-white p-4 shadow-sm hover:border-blue-500 hover:shadow-md transition-all duration-200">
                                    <x-icons.calendar class="h-5 w-5 text-gray-600" />
                                    <span class="font-medium text-gray-900">Connect a CalDAV server</span>
                                </a>
                            @endif
                        </div>

                        {{-- Trust signals --}}
                        <div class="mt-5 flex items-center justify-center gap-5 text-xs text-gray-400">
                            <span class="flex items-center gap-1">
                                <svg class="h-3.5 w-3.5 text-green-500" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25z"/></svg>
                                Read-only access
                            </span>
                            <span class="flex items-center gap-1">
                                <svg class="h-3.5 w-3.5 text-green-500" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z"/></svg>
                                Events never stored
                            </span>
                            <a href="https://spacepad.io/privacy" target="_blank" class="flex items-center gap-1 underline hover:text-gray-600">
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/></svg>
                                Privacy policy
                            </a>
                        </div>

                        <div class="mt-4 text-center">
                            <form action="{{ route('onboarding.skip') }}" method="POST" class="inline">
                                @csrf
                                <button type="submit" class="text-sm text-gray-400 hover:text-gray-600 underline">
                                    Skip for now, I'll connect an account later
                                </button>
                            </form>
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
    <x-modals.select-google-booking-method />
    <x-modals.google-service-account />
@endpush

@push('scripts')
    <script>
        // Show service account modal if needed
        @if(session('open-service-account-modal'))
            window.addEventListener('DOMContentLoaded', function() {
                window.dispatchEvent(new CustomEvent('open-service-account-modal', {
                    detail: { googleAccountId: '{{ session('open-service-account-modal') }}' }
                }));
            });
        @endif

        // Show booking method modal if needed (after write permission selection)
        // Show booking method modal if needed (after connecting Google Workspace account with write permission)
        @if(session('open-google-booking-method-modal'))
            window.addEventListener('DOMContentLoaded', function() {
                window.dispatchEvent(new CustomEvent('open-google-booking-method-modal', {
                    detail: '{{ session('open-google-booking-method-modal') }}'
                }));
            });
        @endif
    </script>
@endpush