<label for="calendar" class="block text-sm font-medium leading-6 text-gray-900">Connected calendar</label>
<div class="mt-1">
    <select name="calendar" id="calendar" class="block w-full rounded-md border-0 py-2 px-3 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6">
        <option value="">Select a calendar</option>
        @foreach($calendars as $calendar)
            <option value="{{ $calendar['id'] . ',' . $calendar['name'] }}">{{ $calendar['name'] }}</option>
        @endforeach
    </select>
</div> 