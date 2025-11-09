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

        <form id="customizationForm" action="{{ route('displays.customization.update', $display) }}" method="POST" enctype="multipart/form-data">
            @csrf
            @method('PUT')
            <div class="space-y-6">
                <div class="border border-gray-200 rounded-lg p-6">
                    <h3 class="text-base font-semibold text-gray-900 mb-4">State Texts</h3>
                    <div class="mb-4">
                        <label for="text_available" class="block text-sm font-medium text-gray-700">Available State Text</label>
                        <input type="text" name="text_available" id="text_available" maxlength="64" placeholder="All yours!" value="{{ old('text_available', \App\Helpers\DisplaySettings::getAvailableText($display)) }}" class="mt-1 px-3 py-2 block w-full border rounded-md border-gray-300 focus:border-blue-500 focus:ring-blue-500 sm:text-sm" />
                    </div>
                    <div class="mb-4">
                        <label for="text_transitioning" class="block text-sm font-medium text-gray-700">Transitioning State Text</label>
                        <input type="text" name="text_transitioning" id="text_transitioning" maxlength="64" placeholder="Keep it short!" value="{{ old('text_transitioning', \App\Helpers\DisplaySettings::getTransitioningText($display)) }}" class="mt-1 px-3 py-2 block w-full border rounded-md border-gray-300 focus:border-blue-500 focus:ring-blue-500 sm:text-sm" />
                    </div>
                    <div class="mb-4">
                        <label for="text_reserved" class="block text-sm font-medium text-gray-700">Reserved State Text</label>
                        <input type="text" name="text_reserved" id="text_reserved" maxlength="64" placeholder="Meeting" value="{{ old('text_reserved', \App\Helpers\DisplaySettings::getReservedText($display)) }}" class="mt-1 px-3 py-2 block w-full border rounded-md border-gray-300 focus:border-blue-500 focus:ring-blue-500 sm:text-sm" />
                    </div>
                    <div class="mb-4">
                        <label for="text_checkin" class="block text-sm font-medium text-gray-700">Check-in State Text</label>
                        <input type="text" name="text_checkin" id="text_checkin" maxlength="64" placeholder="Check in for meeting" value="{{ old('text_checkin', \App\Helpers\DisplaySettings::getCheckInText($display)) }}" class="mt-1 px-3 py-2 block w-full border rounded-md border-gray-300 focus:border-blue-500 focus:ring-blue-500 sm:text-sm" />
                    </div>
                </div>
                <div class="border border-gray-200 rounded-lg p-6">
                    <h3 class="text-base font-semibold text-gray-900 mb-4">Visual Customization</h3>
                    
                    {{-- Logo Upload --}}
                    <div class="mb-6">
                        <label for="logo" class="block text-sm font-medium text-gray-700 mb-2">Display Logo</label>
                        <div class="flex items-center space-x-4">
                            @if(\App\Helpers\DisplaySettings::getLogo($display))
                                <div class="flex-shrink-0">
                                    <img src="{{ route('displays.images', ['display' => $display, 'type' => 'logo']) }}?v={{ $display->updated_at->timestamp }}" alt="Current logo" class="h-16 w-24 object-contain border border-gray-300 rounded">
                                </div>
                                <div>
                                    <p class="text-sm text-gray-500">Current logo</p>
                                    <label class="inline-flex items-center text-sm text-red-600 hover:text-red-500 cursor-pointer">
                                        <input type="checkbox" name="remove_logo" value="1" class="mr-1">
                                        Remove logo
                                    </label>
                                </div>
                            @endif
                        </div>
                        <div class="mt-2">
                            <label for="logo" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-oxford hover:bg-oxford-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-oxford-500 cursor-pointer">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                                </svg>
                                Choose Logo File
                            </label>
                            <input type="file" name="logo" id="logo" accept="image/*" class="hidden">
                            <span id="logo-filename" class="ml-3 text-sm text-gray-500">No file chosen</span>
                        </div>
                        <p class="mt-1 text-xs text-gray-500">Upload a logo image (PNG, JPG, GIF). Recommended size: 200x100px or similar aspect ratio.</p>
                    </div>

                    {{-- Background Image Upload --}}
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Background Image</label>
                        
                        {{-- Current Background --}}
                        @if(\App\Helpers\DisplaySettings::getBackgroundImage($display))
                            <div class="flex items-center space-x-4 mb-4">
                                <div class="flex-shrink-0">
                                    <img src="{{ route('displays.images', ['display' => $display, 'type' => 'background']) }}?v={{ $display->updated_at->timestamp }}" alt="Current background" class="h-16 w-24 object-cover border border-gray-300 rounded">
                                </div>
                                <div>
                                    <p class="text-sm text-gray-500">Current background</p>
                                    <label class="inline-flex items-center text-sm text-red-600 hover:text-red-500 cursor-pointer">
                                        <input type="checkbox" name="remove_background_image" value="1" class="mr-1">
                                        Remove background
                                    </label>
                                </div>
                            </div>
                        @endif
                        
                        {{-- Default Backgrounds Selection --}}
                        <div class="mb-4">
                            <p class="text-sm text-gray-700 mb-2">Default backgrounds</p>
                            <div class="grid grid-cols-4 gap-3">
                                @php
                                    $currentBackground = \App\Helpers\DisplaySettings::getBackgroundImage($display);
                                    $isDefaultBackground = $currentBackground && isset(\App\Services\ImageService::DEFAULT_BACKGROUNDS[$currentBackground]);
                                @endphp
                                @foreach(\App\Services\ImageService::DEFAULT_BACKGROUNDS as $key => $path)
                                    <label class="relative cursor-pointer group col-span-1">
                                        <input type="radio" name="default_background" value="{{ $key }}" class="peer sr-only" 
                                               {{ (old('default_background') === $key || ($isDefaultBackground && $currentBackground === $key)) ? 'checked' : '' }}>
                                        <div class="relative h-20 rounded-lg border-2 border-gray-300 overflow-hidden transition-all peer-checked:border-oxford peer-checked:ring-2 peer-checked:ring-oxford peer-checked:ring-offset-2 hover:border-oxford-400">
                                            <img src="{{ asset($path) }}" alt="Default background {{ $key }}" class="w-full h-full object-cover" onerror="this.src='data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'200\' height=\'120\'%3E%3Crect fill=\'%23ddd\' width=\'200\' height=\'120\'/%3E%3Ctext x=\'50%25\' y=\'50%25\' dominant-baseline=\'middle\' text-anchor=\'middle\' fill=\'%23999\' font-size=\'14\'%3EImage {{ $key }}%3C/text%3E%3C/svg%3E'">
                                            <div class="absolute inset-0 flex items-center justify-center opacity-0 peer-checked:opacity-100 transition-opacity">
                                                <svg class="w-6 h-6 text-white drop-shadow-lg" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                                </svg>
                                            </div>
                                        </div>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                        
                        {{-- Custom Upload --}}
                        <div class="mt-4">
                            <p class="text-sm text-gray-700 mb-2">Or upload custom background</p>
                            <div>
                                <label for="background_image" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-oxford hover:bg-oxford-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-oxford-500 cursor-pointer">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                                    </svg>
                                    Choose Background Image
                                </label>
                                <input type="file" name="background_image" id="background_image" accept="image/*" class="hidden">
                                <span id="background-filename" class="ml-3 text-sm text-gray-500">No file chosen</span>
                            </div>
                            <p class="mt-1 text-xs text-gray-500">Upload a custom background image (PNG, JPG, GIF). Recommended size: 1920x1080px or similar aspect ratio.</p>
                        </div>
                    </div>
                </div>

                <div class="border border-gray-200 rounded-lg p-6">
                    <h3 class="text-base font-semibold text-gray-900 mb-4">Typography</h3>
                    <div class="mb-4">
                        <label for="font_family" class="block text-sm font-medium text-gray-700 mb-2">Font Family</label>
                        <select name="font_family" id="font_family" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                            <option value="Inter" {{ old('font_family', \App\Helpers\DisplaySettings::getFontFamily($display)) === 'Inter' ? 'selected' : '' }}>Inter</option>
                            <option value="Roboto" {{ old('font_family', \App\Helpers\DisplaySettings::getFontFamily($display)) === 'Roboto' ? 'selected' : '' }}>Roboto</option>
                            <option value="Open Sans" {{ old('font_family', \App\Helpers\DisplaySettings::getFontFamily($display)) === 'Open Sans' ? 'selected' : '' }}>Open Sans</option>
                            <option value="Lato" {{ old('font_family', \App\Helpers\DisplaySettings::getFontFamily($display)) === 'Lato' ? 'selected' : '' }}>Lato</option>
                            <option value="Poppins" {{ old('font_family', \App\Helpers\DisplaySettings::getFontFamily($display)) === 'Poppins' ? 'selected' : '' }}>Poppins</option>
                            <option value="Montserrat" {{ old('font_family', \App\Helpers\DisplaySettings::getFontFamily($display)) === 'Montserrat' ? 'selected' : '' }}>Montserrat</option>
                        </select>
                        <p class="mt-1 text-xs text-gray-500">Choose a font family for the display text. Changes will be applied immediately.</p>
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

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Handle logo file selection
            const logoInput = document.getElementById('logo');
            const logoFilename = document.getElementById('logo-filename');
            
            if (logoInput && logoFilename) {
                logoInput.addEventListener('change', function(e) {
                    const file = e.target.files[0];
                    if (file) {
                        logoFilename.textContent = file.name;
                    } else {
                        logoFilename.textContent = 'No file chosen';
                    }
                });
            }

            // Handle background image file selection
            const backgroundInput = document.getElementById('background_image');
            const backgroundFilename = document.getElementById('background-filename');
            
            if (backgroundInput && backgroundFilename) {
                backgroundInput.addEventListener('change', function(e) {
                    const file = e.target.files[0];
                    if (file) {
                        backgroundFilename.textContent = file.name;
                    } else {
                        backgroundFilename.textContent = 'No file chosen';
                    }
                });
            }
        });
    </script>
@endsection
