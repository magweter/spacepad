<label for="room" class="block text-sm font-medium leading-6 text-gray-900">Connected room</label>
<div class="mt-1">
    <select name="room" id="room" class="block w-full rounded-md border-0 py-2 px-3 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6">
        @if(empty($rooms))
            <option value="">No rooms found</option>
        @else
            <option value="">Select a room</option>
            @foreach($rooms as $room)
                <option value="{{ $room['emailAddress'] . ',' . $room['name'] }}">{{ $room['name'] }}</option>
            @endforeach
        @endif
    </select>
</div>
