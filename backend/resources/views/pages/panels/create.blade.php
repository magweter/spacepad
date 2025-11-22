@extends('layouts.base')
@section('title', 'Create a new panel')
@section('container_class', 'max-w-5xl')

@section('content')
    <x-cards.card>
        <x-alerts.alert />

        <form action="{{ route('panels.store') }}" method="POST">
            @csrf

            <div class="space-y-6">
                <div>
                    <label for="name" class="block text-sm font-medium leading-6 text-gray-900">Panel name</label>
                    <div class="mt-2">
                        <input type="text" name="name" id="name" value="{{ old('name') }}" required
                               class="block w-full rounded-md border-0 py-1.5 px-3 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-blue-600 sm:text-sm sm:leading-6"
                               placeholder="e.g., Lobby Panel, Office Overview">
                    </div>
                    <p class="mt-2 text-sm text-gray-500">This name is only used in the dashboard for your identification.</p>
                    @error('name')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="display_mode" class="block text-sm font-medium leading-6 text-gray-900">Display mode</label>
                    <div class="mt-2">
                        <select name="display_mode" id="display_mode" required
                                class="block w-full rounded-md border-0 py-1.5 px-3 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-blue-600 sm:text-sm sm:leading-6">
                            <option value="horizontal" {{ old('display_mode') === 'horizontal' ? 'selected' : '' }}>Horizontal - Rooms side by side</option>
                            <option value="availability" {{ old('display_mode') === 'availability' ? 'selected' : '' }}>Availability View - Grid/calendar view</option>
                        </select>
                    </div>
                    <p class="mt-2 text-sm text-gray-500">Choose how you want the rooms displayed on the panel.</p>
                    @error('display_mode')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium leading-6 text-gray-900">Select displays (up to 4)</label>
                    <p class="mt-1 text-sm text-gray-500">Choose which displays you want to show on this panel. You can select up to 4 displays.</p>
                    
                    @if($displays->isEmpty())
                        <div class="mt-4 rounded-md bg-yellow-50 p-4">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <x-icons.information class="h-5 w-5 text-yellow-400" />
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-sm font-medium text-yellow-800">No displays available</h3>
                                    <div class="mt-2 text-sm text-yellow-700">
                                        <p>You need to create at least one display before you can create a panel.</p>
                                    </div>
                                    <div class="mt-4">
                                        <a href="{{ route('displays.create') }}" class="text-sm font-medium text-yellow-800 underline hover:text-yellow-900">
                                            Create a display →
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="mt-4 space-y-3" id="displaySelection">
                            @foreach($displays as $display)
                                <div class="flex items-center">
                                    <input type="checkbox" name="displays[]" value="{{ $display->id }}" id="display_{{ $display->id }}"
                                           class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-600 display-checkbox"
                                           {{ in_array($display->id, old('displays', [])) ? 'checked' : '' }}>
                                    <label for="display_{{ $display->id }}" class="ml-3 text-sm text-gray-900 cursor-pointer">
                                        <span class="font-medium">{{ $display->name }}</span>
                                        <span class="text-gray-500">({{ $display->display_name }})</span>
                                    </label>
                                </div>
                            @endforeach
                        </div>
                        <p class="mt-2 text-sm text-gray-500" id="selectionCount">0 displays selected (max 4)</p>
                        @error('displays')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                        @error('displays.*')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    @endif
                </div>

                <div class="flex items-center justify-end gap-x-6">
                    <a href="{{ route('panels.index') }}" class="text-sm font-semibold leading-6 text-gray-900">Cancel</a>
                    <button type="submit" 
                            class="rounded-md bg-oxford px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-oxford-600 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-oxford-600"
                            {{ $displays->isEmpty() ? 'disabled' : '' }}>
                        Create panel
                    </button>
                </div>
            </div>
        </form>
    </x-cards.card>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const checkboxes = document.querySelectorAll('.display-checkbox');
            const countElement = document.getElementById('selectionCount');
            const submitButton = document.querySelector('button[type="submit"]');

            function updateSelectionCount() {
                const checked = document.querySelectorAll('.display-checkbox:checked').length;
                countElement.textContent = `${checked} display${checked !== 1 ? 's' : ''} selected (max 4)`;
                
                if (checked > 4) {
                    countElement.classList.add('text-red-600');
                    countElement.classList.remove('text-gray-500');
                    submitButton.disabled = true;
                } else if (checked === 0) {
                    countElement.classList.remove('text-red-600');
                    countElement.classList.add('text-gray-500');
                    submitButton.disabled = true;
                } else {
                    countElement.classList.remove('text-red-600');
                    countElement.classList.add('text-gray-500');
                    submitButton.disabled = false;
                }
            }

            checkboxes.forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    const checked = document.querySelectorAll('.display-checkbox:checked').length;
                    if (checked >= 4 && !this.checked) {
                        // Allow unchecking
                    } else if (checked >= 4 && this.checked) {
                        // Prevent checking more than 4
                        this.checked = false;
                        alert('Maximum 4 displays allowed per panel');
                        return;
                    }
                    updateSelectionCount();
                });
            });

            updateSelectionCount();
        });
    </script>
@endsection

