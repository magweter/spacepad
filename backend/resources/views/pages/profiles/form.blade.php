@extends('layouts.base')
@section('title', $profile ? 'Edit Profile' : 'Create Profile')
@section('container_class', 'max-w-3xl')

@php
    // Resolve the current value for a setting: submitted (old) value first, then the
    // profile's stored value, then the provided default.
    $val = fn ($key, $default = null) => old($key, $settings[$key] ?? $default);
    $bool = fn ($key, $default = false) => (bool) old($key, $settings[$key] ?? $default);

    $inputClass = 'block w-full rounded-md border-0 py-1.5 px-3 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-blue-600 sm:text-sm sm:leading-6';
    $selectClass = 'block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm';
    $checkClass = 'h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-600';
@endphp

@section('content')
    <x-cards.card>
        <x-alerts.alert />

        <form action="{{ $profile ? route('profiles.update', $profile) : route('profiles.store') }}" method="POST">
            @csrf
            @if($profile)
                @method('PUT')
            @endif

            <div class="space-y-8">
                {{-- Name --}}
                <div>
                    <label for="name" class="block text-sm font-medium leading-6 text-gray-900">Profile Name</label>
                    <div class="mt-2">
                        <input type="text" name="name" id="name" value="{{ old('name', $profile?->name) }}"
                               class="{{ $inputClass }}" placeholder="e.g., Meeting rooms NL, Ground floor" required>
                    </div>
                    <p class="mt-2 text-sm text-gray-500">A descriptive name for your own reference.</p>
                </div>

                {{-- Linked displays --}}
                <div>
                    <label class="block text-sm font-medium leading-6 text-gray-900 mb-2">Linked displays</label>
                    <p class="text-sm text-gray-500 mb-3">Selected displays inherit this profile's settings. A per-display setting always overrides the profile.</p>
                    @if($displays->isEmpty())
                        <p class="text-sm text-gray-500">No active displays available in this workspace.</p>
                    @else
                        @php
                            $checkedIds = old('display_ids', $linkedDisplayIds);
                        @endphp
                        <div class="space-y-2 max-h-64 overflow-y-auto border border-gray-200 rounded-md p-4">
                            @foreach($displays as $display)
                                <div class="flex items-center">
                                    <input id="display_{{ $display->id }}" name="display_ids[]" type="checkbox" value="{{ $display->id }}"
                                           class="{{ $checkClass }}"
                                           {{ in_array($display->id, $checkedIds ?? []) ? 'checked' : '' }}>
                                    <label for="display_{{ $display->id }}" class="ml-3 block text-sm text-gray-900">
                                        {{ $display->name }} <span class="text-gray-500">({{ $display->display_name }})</span>
                                    </label>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- Behavior --}}
                <div>
                    <label class="block text-sm font-medium leading-6 text-gray-900 mb-3">Behavior</label>
                    <div class="space-y-3">
                        @foreach([
                            'booking_enabled' => 'Enable booking from the display',
                            'check_in_enabled' => 'Require check-in for meetings',
                            'extend_enabled' => 'Allow extending meetings',
                            'show_organizer' => 'Show the meeting organizer',
                            'allow_future_bookings' => 'Allow booking future time slots',
                            'view_schedule' => 'Show the day schedule',
                            'hide_admin_actions' => 'Hide admin actions on the display',
                        ] as $key => $label)
                            <div class="flex items-center">
                                <input type="hidden" name="{{ $key }}" value="0">
                                <input id="{{ $key }}" name="{{ $key }}" type="checkbox" value="1" class="{{ $checkClass }}" {{ $bool($key) ? 'checked' : '' }}>
                                <label for="{{ $key }}" class="ml-3 block text-sm text-gray-900">{{ $label }}</label>
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- Check-in timing --}}
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="check_in_minutes" class="block text-sm font-medium text-gray-700">Check-in window (minutes)</label>
                        <input type="number" min="1" max="60" name="check_in_minutes" id="check_in_minutes"
                               value="{{ $val('check_in_minutes', 15) }}" class="mt-1 {{ $inputClass }}">
                    </div>
                    <div>
                        <label for="check_in_grace_period" class="block text-sm font-medium text-gray-700">Check-in grace period (minutes)</label>
                        <input type="number" min="1" max="30" name="check_in_grace_period" id="check_in_grace_period"
                               value="{{ $val('check_in_grace_period', 5) }}" class="mt-1 {{ $inputClass }}">
                    </div>
                </div>

                {{-- Selects --}}
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="timeline_widget_mode" class="block text-sm font-medium text-gray-700 mb-1">Timeline widget</label>
                        <select name="timeline_widget_mode" id="timeline_widget_mode" class="{{ $selectClass }}">
                            @foreach(['none' => 'None', 'side_panel' => 'Side panel', 'inline' => 'Inline', 'full_panel' => 'Full panel'] as $v => $l)
                                <option value="{{ $v }}" {{ $val('timeline_widget_mode', 'none') === $v ? 'selected' : '' }}>{{ $l }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="cancel_permission" class="block text-sm font-medium text-gray-700 mb-1">Cancel permission</label>
                        <select name="cancel_permission" id="cancel_permission" class="{{ $selectClass }}">
                            @foreach(['all' => 'Everyone', 'tablet_only' => 'Tablet only', 'none' => 'Nobody'] as $v => $l)
                                <option value="{{ $v }}" {{ $val('cancel_permission', 'all') === $v ? 'selected' : '' }}>{{ $l }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="border_thickness" class="block text-sm font-medium text-gray-700 mb-1">Border thickness</label>
                        <select name="border_thickness" id="border_thickness" class="{{ $selectClass }}">
                            @foreach(['small' => 'Small', 'medium' => 'Medium', 'large' => 'Large'] as $v => $l)
                                <option value="{{ $v }}" {{ $val('border_thickness', 'medium') === $v ? 'selected' : '' }}>{{ $l }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="font_family" class="block text-sm font-medium text-gray-700 mb-1">Font family</label>
                        <select name="font_family" id="font_family" class="{{ $selectClass }}">
                            @foreach(['Inter', 'Roboto', 'Open Sans', 'Lato', 'Poppins', 'Montserrat'] as $font)
                                <option value="{{ $font }}" {{ $val('font_family', 'Inter') === $font ? 'selected' : '' }}>{{ $font }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                {{-- Privacy --}}
                <div>
                    <div class="flex items-center">
                        <input type="hidden" name="show_meeting_title" value="0">
                        <input id="show_meeting_title" name="show_meeting_title" type="checkbox" value="1" class="{{ $checkClass }}" {{ $bool('show_meeting_title', true) ? 'checked' : '' }}>
                        <label for="show_meeting_title" class="ml-3 block text-sm text-gray-900">Show meeting titles</label>
                    </div>
                    <p class="ml-7 text-sm text-gray-500">Uncheck to hide meeting titles for privacy-sensitive environments.</p>
                </div>

                {{-- Custom state texts --}}
                <div>
                    <label class="block text-sm font-medium leading-6 text-gray-900 mb-3">Custom state texts</label>
                    <p class="text-sm text-gray-500 mb-3">Leave empty to use the default text for that state.</p>
                    <div class="grid grid-cols-2 gap-4">
                        @foreach([
                            'text_available' => 'Available',
                            'text_transitioning' => 'Transitioning',
                            'text_reserved' => 'Reserved',
                            'text_checkin' => 'Check-in',
                        ] as $key => $label)
                            <div>
                                <label for="{{ $key }}" class="block text-sm font-medium text-gray-700">{{ $label }}</label>
                                <input type="text" name="{{ $key }}" id="{{ $key }}" value="{{ $val($key) }}" maxlength="255" class="mt-1 {{ $inputClass }}">
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- Advertisement --}}
                <div>
                    <label class="block text-sm font-medium leading-6 text-gray-900 mb-3">Advertisement</label>
                    <div class="flex items-center">
                        <input type="hidden" name="advertisement_enabled" value="0">
                        <input id="advertisement_enabled" name="advertisement_enabled" type="checkbox" value="1" class="{{ $checkClass }}" {{ $bool('advertisement_enabled') ? 'checked' : '' }}>
                        <label for="advertisement_enabled" class="ml-3 block text-sm text-gray-900">Enable advertisement rotation</label>
                    </div>
                    @if(auth()->user()->hasAdvertisementFeature())
                        <div class="grid grid-cols-2 gap-4 mt-3">
                            <div>
                                <label for="advertisement_interval" class="block text-sm font-medium text-gray-700">Interval (minutes)</label>
                                <input type="number" min="1" max="60" name="advertisement_interval" id="advertisement_interval" value="{{ $val('advertisement_interval', 5) }}" class="mt-1 {{ $inputClass }}">
                            </div>
                            <div>
                                <label for="advertisement_duration" class="block text-sm font-medium text-gray-700">Duration (seconds)</label>
                                <input type="number" min="1" max="120" name="advertisement_duration" id="advertisement_duration" value="{{ $val('advertisement_duration', 15) }}" class="mt-1 {{ $inputClass }}">
                            </div>
                        </div>
                    @endif
                    <p class="mt-2 text-sm text-gray-500">Advertisement images are configured per display and are not part of a profile.</p>
                </div>

                <div class="flex items-center justify-end gap-x-6 pt-4 border-t border-gray-200">
                    <a href="{{ route('profiles.index') }}" class="text-sm font-semibold leading-6 text-gray-900">Cancel</a>
                    <button type="submit" class="rounded-md bg-blue-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600">
                        {{ $profile ? 'Update Profile' : 'Create Profile' }}
                    </button>
                </div>
            </div>
        </form>
    </x-cards.card>
@endsection
