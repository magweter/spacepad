@props(['rooms'])

<div>
    <label for="room_id" class="block text-sm font-medium text-gray-700">Connected room</label>
    <select id="room_id" name="room_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
        <option value="">Select a room</option>
        @foreach($rooms as $room)
            <option value="{{ $room['id'] }}">{{ $room['name'] }}</option>
        @endforeach
    </select>
</div> 