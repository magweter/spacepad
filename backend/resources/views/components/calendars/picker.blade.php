<label for="calendar" class="block text-sm font-medium leading-6 text-gray-900">Connected calendar</label>
<div class="mt-1">
    <select name="calendar" id="calendar" class="block w-full rounded-md border-0 py-2 px-3 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6">
        @if(empty($calendars))
            <option value="">No calendars found</option>
        @else
            <option value="">Select a calendar</option>
            @foreach($calendars as $calendar)
                <option value="{{ $calendar['id'] . ',' . $calendar['name'] }}">{{ $calendar['name'] }}</option>
            @endforeach
        @endif
    </select>
</div>

@if(isset($error))
    <div class="mt-4">
        <div class="rounded-md bg-red-50 p-4">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-red-800">Something went wrong</h3>
                    <div class="mt-2 text-sm text-red-700">
                        <p>{{ $error }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endif 