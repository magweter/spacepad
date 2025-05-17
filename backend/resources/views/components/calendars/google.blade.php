@props(['calendars'])

<div>
    <label for="calendar_id" class="block text-sm font-medium text-gray-700">Connected calendar</label>
    <select id="calendar_id" name="calendar_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
        <option value="">Select a calendar</option>
        @foreach($calendars as $calendar)
            <option value="{{ $calendar['id'] }}">{{ $calendar['name'] }}</option>
        @endforeach
    </select>
</div> 