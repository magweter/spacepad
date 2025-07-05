@extends('layouts.base')
@section('title', 'Display Settings - ' . $display->name)
@section('container_class', 'max-w-2xl')

@section('content')
    @if(!auth()->user()->hasPro())
        <x-cards.card>
            <div class="text-center py-12">
                <x-icons.settings class="h-12 w-12 text-gray-400 mx-auto mb-4" />
                <h2 class="text-lg font-semibold text-gray-900 mb-2">Pro Feature</h2>
                <p class="text-gray-600 mb-6">Display settings are only available for Pro users. Upgrade to Pro to customize your display settings.</p>
                <a href="{{ route('dashboard') }}" class="inline-flex items-center rounded-md bg-oxford px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-oxford-600">
                    Back to Dashboard
                </a>
            </div>
        </x-cards.card>
    @else
        <x-cards.card>
        <div class="sm:flex sm:items-center mb-6">
            <div class="sm:flex-auto">
                <h1 class="text-lg font-semibold leading-6 text-gray-900">Display Settings</h1>
                <p class="mt-1 text-sm text-gray-500">Configure settings for "{{ $display->name }}"</p>
            </div>
            <div class="mt-4 sm:ml-16 sm:mt-0 sm:flex-none">
                <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-x-1.5 rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                    <x-icons.arrow-left class="h-4 w-4" />
                    Back to Dashboard
                </a>
            </div>
        </div>

        {{-- Session Status Alert --}}
        <x-alerts.alert :errors="$errors" />

        {{-- Pro Features Notice --}}
        <div class="mb-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
            <div class="flex">
                <div class="flex-shrink-0">
                    <x-icons.information class="h-5 w-5 text-blue-400" />
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-blue-800">Pro Features</h3>
                    <div class="mt-2 text-sm text-blue-700">
                        <p>Display settings are Pro features that allow you to customize how users interact with your displays. These settings control check-in and booking functionality.</p>
                    </div>
                </div>
            </div>
        </div>

        <form id="settingsForm" action="{{ route('displays.settings.update', $display) }}" method="POST">
            @csrf
            @method('PUT')
            
            <div class="space-y-6">
                <!-- Check-in Settings -->
                <div class="border border-gray-200 rounded-lg p-6">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h3 class="text-base font-semibold text-gray-900">Check-in Settings</h3>
                            <p class="text-sm text-gray-500">Allow users to check in to meetings on this display</p>
                        </div>
                        <div class="flex items-center">
                            <input type="checkbox" id="check_in_enabled" name="check_in_enabled" value="1" 
                                   {{ $display->isCheckInEnabled() ? 'checked' : '' }}
                                   class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-600">
                        </div>
                    </div>
                    <div class="text-sm text-gray-600">
                        <p>When enabled, users can check in to meetings directly from this display. This feature allows attendees to mark their attendance for meetings.</p>
                    </div>
                </div>

                <!-- Booking Settings -->
                <div class="border border-gray-200 rounded-lg p-6">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h3 class="text-base font-semibold text-gray-900">Booking Settings</h3>
                            <p class="text-sm text-gray-500">Allow users to book rooms directly from this display</p>
                        </div>
                        <div class="flex items-center">
                            <input type="checkbox" id="booking_enabled" name="booking_enabled" value="1" 
                                   {{ $display->isBookingEnabled() ? 'checked' : '' }}
                                   class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-600">
                        </div>
                    </div>
                    <div class="text-sm text-gray-600">
                        <p>When enabled, users can book the room for immediate use directly from this display. This is a Pro feature that allows quick room reservations.</p>
                    </div>
                </div>

                <!-- Display Information -->
                <div class="border border-gray-200 rounded-lg p-6 bg-gray-50">
                    <h3 class="text-base font-semibold text-gray-900 mb-4">Display Information</h3>
                    <dl class="grid grid-cols-1 gap-x-4 gap-y-4 sm:grid-cols-2">
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Display Name</dt>
                            <dd class="text-sm text-gray-900">{{ $display->display_name }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Calendar</dt>
                            <dd class="text-sm text-gray-900">{{ $display->calendar->name }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Status</dt>
                            <dd class="text-sm">
                                @if($display->status === \App\Enums\DisplayStatus::ACTIVE)
                                    <span class="inline-flex items-center rounded-md bg-green-50 px-2 py-1 text-xs font-medium text-green-700 ring-1 ring-inset ring-green-600/20">Active</span>
                                @else
                                    <span class="inline-flex items-center rounded-md bg-gray-50 px-2 py-1 text-xs font-medium text-gray-600 ring-1 ring-inset ring-gray-500/10">Inactive</span>
                                @endif
                            </dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Last Sync</dt>
                            <dd class="text-sm text-gray-900">
                                @if($display->last_sync_at)
                                    {{ $display->last_sync_at->diffForHumans() }}
                                @else
                                    Never
                                @endif
                            </dd>
                        </div>
                    </dl>
                </div>
            </div>

            <div class="mt-8 flex justify-end gap-x-3">
                <a href="{{ route('dashboard') }}" class="rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                    Cancel
                </a>
                <button type="submit" class="rounded-md bg-oxford px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-oxford-600 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-oxford-600">
                    Save Settings
                </button>
            </div>
        </form>
    </x-cards.card>
    @endif
@endsection

@push('scripts')
<script>
    // Add any JavaScript for form handling if needed
    document.getElementById('settingsForm').addEventListener('submit', function(e) {
        // Form will be submitted normally, but we could add validation here if needed
    });
</script>
@endpush 