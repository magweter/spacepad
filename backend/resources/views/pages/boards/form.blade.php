@extends('layouts.base')
@section('title', $board ? 'Edit Board' : 'Create Board')
@section('container_class', 'max-w-3xl')

@section('content')
    <x-cards.card>
        {{-- Session Status Alert --}}
        <x-alerts.alert />

        <form action="{{ $board ? route('boards.update', $board) : route('boards.store') }}" method="POST" enctype="multipart/form-data">
            @csrf
            @if($board)
                @method('PUT')
            @endif

            <div class="space-y-6">
                <div>
                    <label for="name" class="block text-sm font-medium leading-6 text-gray-900">Board Name</label>
                    <div class="mt-2">
                        <input type="text" name="name" id="name" value="{{ old('name', $board?->name) }}"
                               class="block w-full rounded-md border-0 py-1.5 px-3 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-blue-600 sm:text-sm sm:leading-6"
                               placeholder="e.g., Main Floor, Building A, etc." required>
                    </div>
                    <p class="mt-2 text-sm text-gray-500">Give your board a descriptive name for your own reference. This will not be displayed to your users.</p>
                </div>

                <div>
                    <label for="title" class="block text-sm font-medium leading-6 text-gray-900">Title</label>
                    <div class="mt-2">
                        <input type="text" name="title" id="title" value="{{ old('title', $board?->title) }}"
                               class="block w-full rounded-md border-0 py-1.5 px-3 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-blue-600 sm:text-sm sm:leading-6"
                               placeholder="Custom title for the board">
                    </div>
                    <p class="mt-2 text-sm text-gray-500">The large title displayed top left in the board. If left empty, the title will default to "Meeting Room Overview" in the selected language.</p>
                </div>

                <div>
                    <label for="subtitle" class="block text-sm font-medium leading-6 text-gray-900">Subtitle</label>
                    <div class="mt-2">
                        <input type="text" name="subtitle" id="subtitle" value="{{ old('subtitle', $board?->subtitle) }}"
                               class="block w-full rounded-md border-0 py-1.5 px-3 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-blue-600 sm:text-sm sm:leading-6"
                               placeholder="e.g., 2nd Floor, Building A">
                    </div>
                    <p class="mt-2 text-sm text-gray-500">A smaller subtitle displayed beneith the title. Optional, for example to indicate which floor you're on.</p>
                </div>

                <input type="hidden" name="workspace_id" value="{{ $workspace->id }}">

                <div>
                    <label class="block text-sm font-medium leading-6 text-gray-900 mb-3">Display Selection</label>
                    <div class="space-y-4">
                        <div class="space-y-1">
                            <div class="flex items-center">
                                <input id="show_all_displays_1" name="show_all_displays" type="radio" value="1" 
                                       class="h-4 w-4 border-gray-300 text-blue-600 focus:ring-blue-600"
                                       {{ old('show_all_displays', $board ? ($board->show_all_displays ? '1' : '0') : '1') === '1' ? 'checked' : '' }}
                                       onchange="toggleDisplaySelection()">
                                <label for="show_all_displays_1" class="ml-3 block text-sm font-medium leading-6 text-gray-900">
                                    Show all displays automatically
                                </label>
                            </div>
                            <p class="ml-7 text-sm text-gray-500">All active displays in this workspace will be shown on the board.</p>
                        </div>

                        <div class="space-y-1">
                            <div class="flex items-center">
                                <input id="show_all_displays_0" name="show_all_displays" type="radio" value="0"
                                       class="h-4 w-4 border-gray-300 text-blue-600 focus:ring-blue-600"
                                       {{ old('show_all_displays', $board ? ($board->show_all_displays ? '1' : '0') : '1') === '0' ? 'checked' : '' }}
                                       onchange="toggleDisplaySelection()">
                                <label for="show_all_displays_0" class="ml-3 block text-sm font-medium leading-6 text-gray-900">
                                    Select specific displays
                                </label>
                            </div>
                            <p class="ml-7 text-sm text-gray-500">Choose which displays to show on this board.</p>
                        </div>
                    </div>
                </div>

                <div id="display_selection" class="{{ old('show_all_displays', $board ? ($board->show_all_displays ? '1' : '0') : '1') === '1' ? 'hidden' : '' }}">
                    <label class="block text-sm font-medium leading-6 text-gray-900 mb-3">Select Displays</label>
                    @if($displays->isEmpty())
                        <p class="text-sm text-gray-500">No active displays available in this workspace.</p>
                    @else
                        <div class="space-y-2 max-h-64 overflow-y-auto border border-gray-200 rounded-md p-4">
                            @foreach($displays as $display)
                                <div class="flex items-center">
                                    <input id="display_{{ $display->id }}" name="display_ids[]" type="checkbox" value="{{ $display->id }}"
                                           class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-600"
                                           {{ old('display_ids', $board && !$board->show_all_displays ? $board->displays->pluck('id')->toArray() : []) && in_array($display->id, old('display_ids', $board && !$board->show_all_displays ? $board->displays->pluck('id')->toArray() : [])) ? 'checked' : '' }}>
                                    <label for="display_{{ $display->id }}" class="ml-3 block text-sm text-gray-900">
                                        {{ $display->name }} <span class="text-gray-500">({{ $display->display_name }})</span>
                                    </label>
                                </div>
                            @endforeach
                        </div>
                        <p class="mt-2 text-sm text-gray-500">Select one or more displays to include in this board.</p>
                    @endif
                </div>

                <div>
                    <label class="block text-sm font-medium leading-6 text-gray-900 mb-3">Logo</label>
                    <div class="flex items-center space-x-4">
                        @if($board && $board->logo)
                            <div class="flex-shrink-0">
                                <img src="{{ route('boards.images.logo', $board) }}?v={{ $board->updated_at->timestamp }}" alt="Current logo" class="h-16 w-auto object-contain border border-gray-300 rounded">
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
                        <input type="file" name="logo" id="logo" accept="image/*" class="hidden" onchange="document.getElementById('logo-filename').textContent = this.files[0]?.name || ''">
                        <span id="logo-filename" class="ml-2 text-sm text-gray-500"></span>
                    </div>
                    <p class="mt-2 text-sm text-gray-500">Upload a logo to display in the top left corner of the board. Recommended size: 200x60px or similar aspect ratio.</p>
                </div>

                <div>
                    <label class="block text-sm font-medium leading-6 text-gray-900 mb-3">Display Options</label>
                    <div class="space-y-4">
                        <div class="space-y-1">
                            <div class="flex items-center">
                                <input id="show_title" name="show_title" type="checkbox" value="1"
                                       class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-600"
                                       {{ old('show_title', $board?->show_title ?? true) ? 'checked' : '' }}>
                                <label for="show_title" class="ml-3 block text-sm font-medium leading-6 text-gray-900">
                                    Show event title
                                </label>
                            </div>
                            <p class="ml-7 text-sm text-gray-500">Display the meeting title/event name on the board.</p>
                        </div>

                        <div class="space-y-1">
                            <div class="flex items-center">
                                <input id="show_booker" name="show_booker" type="checkbox" value="1"
                                       class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-600"
                                       {{ old('show_booker', $board?->show_booker ?? true) ? 'checked' : '' }}>
                                <label for="show_booker" class="ml-3 block text-sm font-medium leading-6 text-gray-900">
                                    Show booker/organizer
                                </label>
                            </div>
                            <p class="ml-7 text-sm text-gray-500">Display the name of the person who booked the meeting.</p>
                        </div>

                        <div class="space-y-1">
                            <div class="flex items-center">
                                <input id="show_next_event" name="show_next_event" type="checkbox" value="1"
                                       class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-600"
                                       {{ old('show_next_event', $board?->show_next_event ?? true) ? 'checked' : '' }}>
                                <label for="show_next_event" class="ml-3 block text-sm font-medium leading-6 text-gray-900">
                                    Show 'next up' event
                                </label>
                            </div>
                            <p class="ml-7 text-sm text-gray-500">Display upcoming events when a room is currently available.</p>
                        </div>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium leading-6 text-gray-900 mb-3">Transitioning Settings</label>
                    <div class="space-y-4">
                        <div class="space-y-1">
                            <div class="flex items-center">
                                <input id="show_transitioning" name="show_transitioning" type="checkbox" value="1"
                                       class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-600"
                                       {{ old('show_transitioning', $board?->show_transitioning ?? true) ? 'checked' : '' }}>
                                <label for="show_transitioning" class="ml-3 block text-sm font-medium leading-6 text-gray-900">
                                    Show transitioning state
                                </label>
                            </div>
                            <p class="ml-7 text-sm text-gray-500">Display the transitioning state when a meeting is ending or starting soon.</p>
                        </div>

                        <div class="space-y-1">
                            <label for="transitioning_minutes" class="block text-sm font-medium text-gray-700">Transitioning Minutes</label>
                            <input type="number" min="1" max="60" name="transitioning_minutes" id="transitioning_minutes" value="{{ old('transitioning_minutes', $board?->transitioning_minutes ?? 10) }}" class="mt-1 px-3 py-2 block w-32 border rounded-md border-gray-300 focus:border-blue-500 focus:ring-blue-500 sm:text-sm" />
                            <p class="mt-1 text-sm text-gray-500">Display rooms as transitioning (orange) when a meeting is ending or starting within this many minutes. Default: 10 minutes.</p>
                        </div>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium leading-6 text-gray-900 mb-3">Typography</label>
                    <div class="space-y-1">
                        <label for="font_family" class="block text-sm font-medium text-gray-700 mb-2">Font Family</label>
                        <select name="font_family" id="font_family" class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                            <option value="Inter" {{ old('font_family', $board?->font_family ?? 'Inter') === 'Inter' ? 'selected' : '' }}>Inter</option>
                            <option value="Roboto" {{ old('font_family', $board?->font_family ?? 'Inter') === 'Roboto' ? 'selected' : '' }}>Roboto</option>
                            <option value="Open Sans" {{ old('font_family', $board?->font_family ?? 'Inter') === 'Open Sans' ? 'selected' : '' }}>Open Sans</option>
                            <option value="Lato" {{ old('font_family', $board?->font_family ?? 'Inter') === 'Lato' ? 'selected' : '' }}>Lato</option>
                            <option value="Poppins" {{ old('font_family', $board?->font_family ?? 'Inter') === 'Poppins' ? 'selected' : '' }}>Poppins</option>
                            <option value="Montserrat" {{ old('font_family', $board?->font_family ?? 'Inter') === 'Montserrat' ? 'selected' : '' }}>Montserrat</option>
                        </select>
                        <p class="mt-1 text-sm text-gray-500">Choose a font family for the board text.</p>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium leading-6 text-gray-900 mb-3">Display Settings</label>
                    <div class="space-y-4">
                        <div class="space-y-1">
                            <label for="view_mode" class="block text-sm font-medium text-gray-700 mb-2">View Mode</label>
                            <select name="view_mode" id="view_mode" class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                <option value="card" {{ old('view_mode', $board?->view_mode ?? 'card') === 'card' ? 'selected' : '' }}>Card View</option>
                                <option value="table" {{ old('view_mode', $board?->view_mode ?? 'card') === 'table' ? 'selected' : '' }}>Table View</option>
                                <option value="grid" {{ old('view_mode', $board?->view_mode ?? 'card') === 'grid' ? 'selected' : '' }}>Grid View</option>
                            </select>
                            <p class="mt-1 text-sm text-gray-500">Choose how displays are displayed on the board.</p>
                        </div>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium leading-6 text-gray-900 mb-3">Language</label>
                    <div class="space-y-1">
                        <label for="language" class="block text-sm font-medium text-gray-700 mb-2">Display Language</label>
                        <select name="language" id="language" class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                            <option value="en" {{ old('language', $board?->language ?? 'en') === 'en' ? 'selected' : '' }}>English</option>
                            <option value="nl" {{ old('language', $board?->language ?? 'en') === 'nl' ? 'selected' : '' }}>Nederlands</option>
                            <option value="fr" {{ old('language', $board?->language ?? 'en') === 'fr' ? 'selected' : '' }}>Français</option>
                            <option value="de" {{ old('language', $board?->language ?? 'en') === 'de' ? 'selected' : '' }}>Deutsch</option>
                            <option value="es" {{ old('language', $board?->language ?? 'en') === 'es' ? 'selected' : '' }}>Español</option>
                            <option value="sv" {{ old('language', $board?->language ?? 'en') === 'sv' ? 'selected' : '' }}>Svenska</option>
                        </select>
                        <p class="mt-1 text-sm text-gray-500">Choose the language for date and time formatting on the board.</p>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium leading-6 text-gray-900 mb-3">Privacy</label>
                    <div class="space-y-1">
                        <div class="flex items-center">
                            <input id="show_meeting_title" name="show_meeting_title" type="checkbox" value="1"
                                   class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-600"
                                   {{ old('show_meeting_title', $board?->show_meeting_title ?? true) ? 'checked' : '' }}>
                            <label for="show_meeting_title" class="ml-3 block text-sm font-medium leading-6 text-gray-900">
                                Show meeting titles
                            </label>
                        </div>
                        <p class="ml-7 text-sm text-gray-500">If unchecked, meeting titles will be hidden for privacy-sensitive environments.</p>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium leading-6 text-gray-900 mb-3">Theme</label>
                    <div class="space-y-4">
                        <div class="space-y-1">
                            <div class="flex items-center">
                                <input id="theme_dark" name="theme" type="radio" value="dark" 
                                       class="h-4 w-4 border-gray-300 text-blue-600 focus:ring-blue-600"
                                       {{ old('theme', $board?->theme ?? 'dark') === 'dark' ? 'checked' : '' }}>
                                <label for="theme_dark" class="ml-3 block text-sm font-medium leading-6 text-gray-900">
                                    Dark mode
                                </label>
                            </div>
                            <p class="ml-7 text-sm text-gray-500">Dark background with light text for better visibility in low-light environments.</p>
                        </div>

                        <div class="space-y-1">
                            <div class="flex items-center">
                                <input id="theme_light" name="theme" type="radio" value="light"
                                       class="h-4 w-4 border-gray-300 text-blue-600 focus:ring-blue-600"
                                       {{ old('theme', $board?->theme ?? 'dark') === 'light' ? 'checked' : '' }}>
                                <label for="theme_light" class="ml-3 block text-sm font-medium leading-6 text-gray-900">
                                    Light mode
                                </label>
                            </div>
                            <p class="ml-7 text-sm text-gray-500">Light background with dark text for better visibility in bright environments.</p>
                        </div>

                        <div class="space-y-1">
                            <div class="flex items-center">
                                <input id="theme_system" name="theme" type="radio" value="system"
                                       class="h-4 w-4 border-gray-300 text-blue-600 focus:ring-blue-600"
                                       {{ old('theme', $board?->theme ?? 'dark') === 'system' ? 'checked' : '' }}>
                                <label for="theme_system" class="ml-3 block text-sm font-medium leading-6 text-gray-900">
                                    System preference
                                </label>
                            </div>
                            <p class="ml-7 text-sm text-gray-500">Automatically match your device's dark/light mode preference.</p>
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-x-6 pt-4 border-t border-gray-200">
                    <a href="{{ route('dashboard') }}?tab=boards" class="text-sm font-semibold leading-6 text-gray-900">Cancel</a>
                    <button type="submit" class="rounded-md bg-blue-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600">
                        {{ $board ? 'Update Board' : 'Create Board' }}
                    </button>
                </div>
            </div>
        </form>
    </x-cards.card>
@endsection

@push('scripts')
    <script>
        function toggleDisplaySelection() {
            const showAll = document.getElementById('show_all_displays_1').checked;
            const displaySelection = document.getElementById('display_selection');
            
            if (showAll) {
                displaySelection.classList.add('hidden');
                // Uncheck all display checkboxes
                document.querySelectorAll('input[name="display_ids[]"]').forEach(checkbox => {
                    checkbox.checked = false;
                });
            } else {
                displaySelection.classList.remove('hidden');
            }
        }

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            toggleDisplaySelection();
        });
    </script>
@endpush
