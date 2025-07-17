@extends('layouts.base')
@section('title', 'Display Customization - ' . $display->name)
@section('container_class', 'max-w-2xl')

@section('content')
    @if(!auth()->user()->hasPro())
        <x-cards.card>
            <div class="text-center py-12">
                <x-icons.settings class="h-12 w-12 text-gray-400 mx-auto mb-4" />
                <h2 class="text-lg font-semibold text-gray-900 mb-2">Pro Feature</h2>
                <p class="text-gray-600 mb-6">Display customization is only available for Pro users. Upgrade to Pro to customize your display texts.</p>
                <a href="{{ route('dashboard') }}" class="inline-flex items-center rounded-md bg-oxford px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-oxford-600">
                    Back to Dashboard
                </a>
            </div>
        </x-cards.card>
    @else
        <x-cards.card>
        <div class="sm:flex sm:items-center mb-6">
            <div class="sm:flex-auto">
                <h1 class="text-lg font-semibold leading-6 text-gray-900">Display Customization</h1>
                <p class="mt-1 text-sm text-gray-500">Customize the texts and privacy options for "{{ $display->name }}"</p>
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
        @if(session('success'))
            <div class="mb-4 p-3 bg-green-50 border border-green-200 text-green-800 rounded">{{ session('success') }}</div>
        @endif

        <form id="customizationForm" action="{{ route('displays.customization.update', $display) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="space-y-6">
                <div class="border border-gray-200 rounded-lg p-6">
                    <h3 class="text-base font-semibold text-gray-900 mb-4">State Texts</h3>
                    <div class="mb-4">
                        <label for="text_available" class="block text-sm font-medium text-gray-700">Available State Text</label>
                        <input type="text" name="text_available" id="text_available" maxlength="64" value="{{ old('text_available', \App\Helpers\DisplaySettings::getAvailableText($display)) }}" class="mt-1 px-3 py-2 block w-full border rounded-md border-gray-300 focus:border-blue-500 focus:ring-blue-500 sm:text-sm" required />
                    </div>
                    <div class="mb-4">
                        <label for="text_transitioning" class="block text-sm font-medium text-gray-700">Transitioning State Text</label>
                        <input type="text" name="text_transitioning" id="text_transitioning" maxlength="64" value="{{ old('text_transitioning', \App\Helpers\DisplaySettings::getTransitioningText($display)) }}" class="mt-1 px-3 py-2 block w-full border rounded-md border-gray-300 focus:border-blue-500 focus:ring-blue-500 sm:text-sm" required />
                    </div>
                    <div class="mb-4">
                        <label for="text_reserved" class="block text-sm font-medium text-gray-700">Reserved State Text</label>
                        <input type="text" name="text_reserved" id="text_reserved" maxlength="64" value="{{ old('text_reserved', \App\Helpers\DisplaySettings::getReservedText($display)) }}" class="mt-1 px-3 py-2 block w-full border rounded-md border-gray-300 focus:border-blue-500 focus:ring-blue-500 sm:text-sm" required />
                    </div>
                    <div class="mb-4">
                        <label for="text_checkin" class="block text-sm font-medium text-gray-700">Check-in State Text</label>
                        <input type="text" name="text_checkin" id="text_checkin" maxlength="64" value="{{ old('text_checkin', \App\Helpers\DisplaySettings::getCheckInText($display)) }}" class="mt-1 px-3 py-2 block w-full border rounded-md border-gray-300 focus:border-blue-500 focus:ring-blue-500 sm:text-sm" required />
                    </div>
                </div>
                <div class="border border-gray-200 rounded-lg p-6">
                    <h3 class="text-base font-semibold text-gray-900 mb-4">Privacy</h3>
                    <div class="flex items-center">
                        <input type="checkbox" id="show_meeting_title" name="show_meeting_title" value="1" {{ old('show_meeting_title', \App\Helpers\DisplaySettings::getShowMeetingTitle($display)) ? 'checked' : '' }} class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-600">
                        <label for="show_meeting_title" class="ml-2 block text-sm text-gray-700">Show meeting title on display</label>
                    </div>
                    <p class="mt-1 text-xs text-gray-500">If unchecked, meeting titles will be hidden for privacy-sensitive environments.</p>
                </div>
            </div>
            <div class="mt-8 flex justify-end gap-x-3">
                <a href="{{ route('dashboard') }}" class="rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                    Cancel
                </a>
                <button type="submit" class="rounded-md bg-oxford px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-oxford-600 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-oxford-600">
                    Save Customization
                </button>
            </div>
        </form>
    </x-cards.card>
    @endif
@endsection 