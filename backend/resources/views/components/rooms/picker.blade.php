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

@if($type === \App\Enums\Provider::GOOGLE)
<div id="roomWarning" class="mt-4 hidden">
    <div class="rounded-md bg-yellow-50 p-4">
        <div class="flex items-start">
            <div class="flex-shrink-0">
                <svg class="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
                </svg>
            </div>
            <div class="flex-1 pl-2">
                <h3 class="text-sm font-medium text-yellow-800">Important: Calendar Read Access Required</h3>
                <div class="mt-2 text-sm text-yellow-700">
                    <p>
                        By default, organizational admins should have access to all rooms. To ensure you can view calendar events, you can test your access by adding the room's calendar to your Google Calendar. Here's how:
                    </p>
                    <ol class="list-decimal list-inside mt-2 space-y-1">
                        <li>Open Google Calendar</li>
                        <li>Click the "+" next to "Other calendars"</li>
                        <li>Select "Subscribe to calendar"</li>
                        <li>Enter the room's email address</li>
                        <li>Click "Add calendar"</li>
                    </ol>
                </div>
            </div>
            <div class="ml-6 flex-shrink-0">
                <img src="{{ asset('images/gcal-instruction.png') }}" alt="Google Calendar Instructions" class="h-32 w-auto rounded-lg border border-gray-200">
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('room').addEventListener('change', function() {
    const warning = document.getElementById('roomWarning');
    warning.classList.toggle('hidden', !this.value);
});
</script>
@endif
