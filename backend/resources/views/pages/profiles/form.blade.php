@extends('layouts.base')
@section('title', $profile ? 'Edit Profile' : 'Create Profile')
@section('container_class', 'max-w-3xl')

@php
    // Resolve the current value for a setting: submitted (old) value first, then the
    // profile's stored value, then the provided default.
    $val = fn ($key, $default = null) => old($key, $settings[$key] ?? $default);
    $bool = fn ($key, $default = false) => (bool) old($key, $settings[$key] ?? $default);

    $inputClass = 'block w-full rounded-md border-0 py-1.5 px-3 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-blue-600 sm:text-sm sm:leading-6';
    $narrowClass = 'px-3 py-2 block w-32 border rounded-md border-gray-300 focus:border-blue-500 focus:ring-blue-500 sm:text-sm';
    // bg-white is load-bearing: without it a select falls back to the browser's own control
    // background, which disappears against the gray-50 panels.
    $selectClass = 'block w-full px-3 py-2 border border-gray-300 bg-white rounded-md shadow-sm text-gray-900 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm';
    $checkClass = 'h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-600';
    $radioClass = 'h-4 w-4 border-gray-300 text-blue-600 focus:ring-blue-600';

    $currentBackground = $val('background_image');
    $isDefaultBackground = $currentBackground && isset(\App\Services\ImageService::DEFAULT_BACKGROUNDS[$currentBackground]);
@endphp

@section('content')
    <x-cards.card>
        <x-alerts.alert :errors="$errors" />

        <form action="{{ $profile ? route('profiles.update', $profile) : route('profiles.store') }}" method="POST" enctype="multipart/form-data">
            @csrf
            @if($profile)
                @method('PUT')
            @endif

            <div class="space-y-8">
                {{-- Name --}}
                <div>
                    <label for="name" class="block text-sm font-medium leading-6 text-gray-900">Profile Name</label>
                    <div class="mt-2">
                        <input type="text" name="name" id="name" value="{{ old('name', $profile?->name) }}"
                               class="{{ $inputClass }}" placeholder="e.g., Meeting rooms NL, Ground floor" required>
                    </div>
                    <p class="mt-2 text-sm text-gray-500">A descriptive name for your own reference. It is never shown on a display.</p>
                </div>

                {{-- Used by (read-only: assigning happens from the Displays tab, so there is one owner
                     of the link and a display can never be silently taken from another profile) --}}
                <div>
                    <label class="block text-sm font-medium leading-6 text-gray-900 mb-2">Used by</label>
                    @if($linkedDisplays->isEmpty())
                        <p class="text-sm text-gray-500">
                            No displays follow this profile yet. Select displays in the
                            <a href="{{ route('dashboard', ['tab' => 'displays']) }}" class="text-blue-600 hover:text-blue-500">Displays tab</a>
                            and assign this profile there.
                        </p>
                    @else
                        <p class="text-sm text-gray-500 mb-2">
                            {{ $linkedDisplays->count() }} {{ $linkedDisplays->count() === 1 ? 'display follows' : 'displays follow' }}
                            this profile. Change that from the
                            <a href="{{ route('dashboard', ['tab' => 'displays']) }}" class="text-blue-600 hover:text-blue-500">Displays tab</a>.
                        </p>
                        <div class="flex flex-wrap gap-2">
                            @foreach($linkedDisplays as $linked)
                                <span class="inline-flex items-center rounded-md bg-gray-50 px-2 py-1 text-xs font-medium text-gray-700 ring-1 ring-inset ring-gray-200">
                                    {{ $linked->name ?: $linked->display_name }}
                                </span>
                            @endforeach
                        </div>
                    @endif
                    <p class="mt-2 text-xs text-gray-500">
                        A display follows this profile per section. If someone changes, say, the branding on one
                        display, only that section stops following this profile. The rest keeps updating.
                    </p>
                </div>

                {{-- Behavior --}}
                <div class="border border-gray-200 rounded-lg p-6">
                    <h3 class="text-base font-semibold text-gray-900">Behavior</h3>
                    <p class="mt-1 text-sm text-gray-500 mb-4">What people can do from the display: check in, book, extend and cancel.</p>

                    <div class="space-y-4">
                        <div class="flex items-start gap-3">
                            <input type="hidden" name="check_in_enabled" value="0">
                            <input id="check_in_enabled" name="check_in_enabled" type="checkbox" value="1" class="mt-0.5 {{ $checkClass }}" {{ $bool('check_in_enabled') ? 'checked' : '' }}>
                            <label for="check_in_enabled" class="text-sm text-gray-900">Require check-in for meetings</label>
                            <x-info-tip text="Attendees must confirm on the display that the meeting is happening. If nobody checks in within the grace period, the room is released and shows as available again, so no-show bookings stop blocking the room." />
                        </div>

                        <div class="grid grid-cols-2 gap-4 pl-7">
                            <div>
                                <label for="check_in_minutes" class="flex items-center gap-1.5 text-sm font-medium text-gray-700">
                                    Check-in window (minutes before)
                                    <x-info-tip text="How long before the start time the check-in button appears. With 15, someone can confirm the meeting from 08:45 for a 09:00 booking." />
                                </label>
                                <input type="number" min="1" max="60" name="check_in_minutes" id="check_in_minutes"
                                       value="{{ $val('check_in_minutes', 15) }}" class="mt-1 {{ $narrowClass }}">
                            </div>
                            <div>
                                <label for="check_in_grace_period" class="flex items-center gap-1.5 text-sm font-medium text-gray-700">
                                    Grace period (minutes after)
                                    <x-info-tip text="How long after the start time check-in is still possible. Once this passes without a check-in, the booking is released and the room frees up." />
                                </label>
                                <input type="number" min="1" max="30" name="check_in_grace_period" id="check_in_grace_period"
                                       value="{{ $val('check_in_grace_period', 5) }}" class="mt-1 {{ $narrowClass }}">
                            </div>
                        </div>

                        <div class="flex items-start gap-3">
                            <input type="hidden" name="booking_enabled" value="0">
                            <input id="booking_enabled" name="booking_enabled" type="checkbox" value="1" class="mt-0.5 {{ $checkClass }}" {{ $bool('booking_enabled') ? 'checked' : '' }}>
                            <label for="booking_enabled" class="text-sm text-gray-900">Allow booking from the display</label>
                            <x-info-tip text="Adds booking buttons to the tablet. Bookings are written back to the connected calendar, which requires write permission on that account. With read-only access the booking will fail." />
                        </div>

                        <div class="flex items-start gap-3 pl-7">
                            <input type="hidden" name="allow_future_bookings" value="0">
                            <input id="allow_future_bookings" name="allow_future_bookings" type="checkbox" value="1" class="mt-0.5 {{ $checkClass }}" {{ $bool('allow_future_bookings') ? 'checked' : '' }}>
                            <label for="allow_future_bookings" class="text-sm text-gray-900">Allow booking future time slots</label>
                            <x-info-tip text="Without this, people can only book the room for today. With it, they can pick another date from the tablet." />
                        </div>

                        <div class="flex items-start gap-3">
                            <input type="hidden" name="extend_enabled" value="0">
                            <input id="extend_enabled" name="extend_enabled" type="checkbox" value="1" class="mt-0.5 {{ $checkClass }}" {{ $bool('extend_enabled') ? 'checked' : '' }}>
                            <label for="extend_enabled" class="text-sm text-gray-900">Allow extending meetings</label>
                            <x-info-tip text="Adds +15/+30/+60 minute buttons during a meeting. Extending an event from an external calendar needs write permission, and it fails when the next booking would overlap." />
                        </div>

                        <div class="flex items-start gap-3">
                            <input type="hidden" name="hide_admin_actions" value="0">
                            <input id="hide_admin_actions" name="hide_admin_actions" type="checkbox" value="1" class="mt-0.5 {{ $checkClass }}" {{ $bool('hide_admin_actions') ? 'checked' : '' }}>
                            <label for="hide_admin_actions" class="text-sm text-gray-900">Hide admin actions on the display</label>
                            <x-info-tip text="Hides switch-room and logout, so visitors cannot reconfigure a public tablet. Admins still reach them by long-pressing the room name; the buttons then show for 30 seconds." />
                        </div>

                        <div class="pt-4 border-t border-gray-100">
                            <p class="flex items-center gap-1.5 text-sm font-medium text-gray-700 mb-2">
                                Who can cancel a meeting
                                <x-info-tip text="Controls the End meeting button. 'Tablet bookings only' lets people end what was booked on the tablet but never a meeting from someone's calendar, the safest choice for shared calendars." />
                            </p>
                            <div class="space-y-2">
                                @foreach(['all' => 'Everyone: any event can be cancelled (default)', 'tablet_only' => 'Tablet bookings only', 'none' => 'Nobody: cancelling is disabled'] as $v => $l)
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="radio" name="cancel_permission" value="{{ $v }}" class="{{ $radioClass }}" {{ $val('cancel_permission', 'all') === $v ? 'checked' : '' }}>
                                        <span class="text-sm text-gray-700">{{ $l }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Display --}}
                <div class="border border-gray-200 rounded-lg p-6">
                    <h3 class="text-base font-semibold text-gray-900">Display</h3>
                    <p class="mt-1 text-sm text-gray-500 mb-4">What the display shows: schedule, timeline and meeting details.</p>

                    <div class="space-y-4">
                        <div>
                            <p class="flex items-center gap-1.5 text-sm font-medium text-gray-700 mb-2">
                                Today's schedule
                                <x-info-tip text="How the day's bookings are shown. A side panel keeps the status screen clean and opens on tap; inline and full-height keep the timeline permanently in view, which suits larger screens." />
                            </p>
                            <div class="space-y-2">
                                @foreach([
                                    'none' => ['Disabled', 'No timeline shown on the display'],
                                    'side_panel' => ['Side panel', 'Slides over when users tap the calendar icon'],
                                    'inline' => ['Inline', 'Always visible next to the content, vertically centered'],
                                    'full_panel' => ['Full-height panel', 'Full-height timeline on the right side'],
                                ] as $v => [$title, $hint])
                                    <label class="flex items-start gap-3 cursor-pointer">
                                        <input type="radio" name="timeline_widget_mode" value="{{ $v }}" class="mt-0.5 {{ $radioClass }}" {{ $val('timeline_widget_mode', 'none') === $v ? 'checked' : '' }}>
                                        <div>
                                            <span class="text-sm font-medium text-gray-900">{{ $title }}</span>
                                            <p class="text-xs text-gray-500">{{ $hint }}</p>
                                        </div>
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        <div class="flex items-start gap-3 pt-4 border-t border-gray-100">
                            <input type="hidden" name="view_schedule" value="0">
                            <input id="view_schedule" name="view_schedule" type="checkbox" value="1" class="mt-0.5 {{ $checkClass }}" {{ $bool('view_schedule') ? 'checked' : '' }}>
                            <label for="view_schedule" class="text-sm text-gray-900">Show the day schedule button</label>
                            <x-info-tip text="Adds a button that opens today's full schedule as an overlay. Can be combined with any of the timeline options above." />
                        </div>

                        <div class="flex items-start gap-3">
                            <input type="hidden" name="show_organizer" value="0">
                            <input id="show_organizer" name="show_organizer" type="checkbox" value="1" class="mt-0.5 {{ $checkClass }}" {{ $bool('show_organizer') ? 'checked' : '' }}>
                            <label for="show_organizer" class="text-sm text-gray-900">Show the meeting organizer</label>
                            <x-info-tip text="Shows who booked the meeting, taken from the calendar event. Consider leaving this off in public areas where names should not be readable by visitors." />
                        </div>

                        <div class="flex items-start gap-3">
                            <input type="hidden" name="show_meeting_title" value="0">
                            <input id="show_meeting_title" name="show_meeting_title" type="checkbox" value="1" class="mt-0.5 {{ $checkClass }}" {{ $bool('show_meeting_title', true) ? 'checked' : '' }}>
                            <label for="show_meeting_title" class="text-sm text-gray-900">Show meeting titles</label>
                            <x-info-tip text="When off, the title is replaced by the Reserved text below. Useful when subjects can be confidential, such as '1-on-1' or a client name." />
                        </div>


                        <div class="flex items-start gap-3">
                            <input type="hidden" name="show_meeting_location" value="0">
                            <input id="show_meeting_location" name="show_meeting_location" type="checkbox" value="1" class="mt-0.5 {{ $checkClass }}" {{ $bool('show_meeting_location') ? 'checked' : '' }}>
                            <label for="show_meeting_location" class="text-sm text-gray-900">Show the meeting location</label>
                            <x-info-tip text="The location line of the calendar event, under each meeting in the day schedule. Google and Microsoft put the booked room and the address on that same line, so it can read as a full street and postcode followed by a room name. Not the same as the room name above, which is this display's own name." />
                        </div>

                        <div class="pt-4 border-t border-gray-100">
                            <p class="flex items-center gap-1.5 text-sm font-medium text-gray-700 mb-2">
                                Border thickness
                                <x-info-tip text="Thickness of the coloured status border around the screen. Large reads clearly from a corridor; small suits a tablet you stand right in front of." />
                            </p>
                            <div class="space-y-2">
                                @foreach(['small' => 'Small: thin borders, minimalist', 'medium' => 'Medium: standard (default)', 'large' => 'Large: thick borders, better visibility'] as $v => $l)
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="radio" name="border_thickness" value="{{ $v }}" class="{{ $radioClass }}" {{ $val('border_thickness', 'medium') === $v ? 'checked' : '' }}>
                                        <span class="text-sm text-gray-700">{{ $l }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>

                {{-- State texts --}}
                <div class="border border-gray-200 rounded-lg p-6">
                    <h3 class="flex items-center gap-1.5 text-base font-semibold text-gray-900">
                        State texts
                        <x-info-tip text="The wording shown big on the screen per room state. Leave a field empty to keep the built-in default text." />
                    </h3>
                    <p class="mt-1 text-sm text-gray-500 mb-4">Custom wording per room state.</p>
                    <div class="grid grid-cols-2 gap-4">
                        @foreach([
                            'text_available' => ['Available', 'All yours!'],
                            'text_transitioning' => ['Transitioning', 'Keep it short!'],
                            'text_reserved' => ['Reserved', 'Meeting'],
                            'text_checkin' => ['Check-in', 'Check in for meeting'],
                        ] as $key => [$label, $placeholder])
                            <div>
                                <label for="{{ $key }}" class="block text-sm font-medium text-gray-700">{{ $label }}</label>
                                <input type="text" name="{{ $key }}" id="{{ $key }}" value="{{ $val($key) }}" maxlength="64"
                                       placeholder="{{ $placeholder }}" class="mt-1 {{ $inputClass }}">
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- Branding --}}
                <div class="border border-gray-200 rounded-lg p-6">
                    <h3 class="text-base font-semibold text-gray-900">Branding</h3>
                    <p class="mt-1 text-sm text-gray-500 mb-4">Font, logo and background for every display that follows this profile.</p>

                    <div class="space-y-6">
                        <div>
                            <label for="font_family" class="block text-sm font-medium text-gray-700 mb-1">Font family</label>
                            <select name="font_family" id="font_family" class="{{ $selectClass }}">
                                @foreach(['Inter', 'Roboto', 'Open Sans', 'Lato', 'Poppins', 'Montserrat'] as $font)
                                    <option value="{{ $font }}" {{ $val('font_family', 'Inter') === $font ? 'selected' : '' }}>{{ $font }}</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Logo --}}
                        <div class="pt-4 border-t border-gray-100">
                            <label class="flex items-center gap-1.5 text-sm font-medium text-gray-700 mb-2">
                                Logo
                                <x-info-tip text="Shown on every display that follows this profile. A display can still upload its own logo, which detaches only its Branding section from this profile." />
                            </label>
                            @if($profile && $val('logo'))
                                <div class="flex items-center space-x-4 mb-2">
                                    <img src="{{ route('profiles.images', ['profile' => $profile, 'type' => 'logo']) }}?v={{ $profile->updated_at->timestamp }}" alt="Current logo" class="h-16 w-24 object-contain border border-gray-300 rounded">
                                    <label class="inline-flex items-center text-sm text-red-600 hover:text-red-500 cursor-pointer">
                                        <input type="checkbox" name="remove_logo" value="1" class="mr-1">
                                        Remove logo
                                    </label>
                                </div>
                            @endif
                            <label for="logo" class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-md text-white bg-oxford hover:bg-oxford-600 cursor-pointer">
                                Choose logo file
                            </label>
                            <input type="file" name="logo" id="logo" accept="image/*" class="hidden">
                            <span id="logo-filename" class="ml-3 text-sm text-gray-500">No file chosen</span>
                            <p class="mt-1 text-xs text-gray-500">PNG, JPG or GIF. Recommended around 200x100px.</p>
                        </div>

                        {{-- Background --}}
                        <div class="pt-4 border-t border-gray-100">
                            <label class="flex items-center gap-1.5 text-sm font-medium text-gray-700 mb-2">
                                Background
                                <x-info-tip text="Fills the screen behind the room status. Pick a bundled background or upload your own; keep it low-contrast so the status text stays readable from a distance." />
                            </label>
                            @if($profile && $currentBackground)
                                <div class="flex items-center space-x-4 mb-4">
                                    <img src="{{ route('profiles.images', ['profile' => $profile, 'type' => 'background']) }}?v={{ $profile->updated_at->timestamp }}" alt="Current background" class="h-16 w-24 object-cover border border-gray-300 rounded">
                                    <label class="inline-flex items-center text-sm text-red-600 hover:text-red-500 cursor-pointer">
                                        <input type="checkbox" name="remove_background_image" value="1" class="mr-1">
                                        Remove background
                                    </label>
                                </div>
                            @endif
                            <p class="text-sm text-gray-700 mb-2">Bundled backgrounds</p>
                            <div class="grid grid-cols-4 gap-3">
                                @foreach(\App\Services\ImageService::DEFAULT_BACKGROUNDS as $key => $path)
                                    <label class="relative cursor-pointer group col-span-1">
                                        <input type="radio" name="default_background" value="{{ $key }}" class="peer sr-only"
                                               {{ (old('default_background') === $key || ($isDefaultBackground && $currentBackground === $key)) ? 'checked' : '' }}>
                                        <div class="relative h-20 rounded-lg border-2 border-gray-300 overflow-hidden transition-all peer-checked:border-oxford peer-checked:ring-2 peer-checked:ring-oxford peer-checked:ring-offset-2 hover:border-oxford-400">
                                            <img src="{{ asset($path) }}" alt="Background {{ $key }}" class="w-full h-full object-cover">
                                        </div>
                                    </label>
                                @endforeach
                            </div>
                            <div class="mt-4">
                                <label for="background_image" class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-md text-white bg-oxford hover:bg-oxford-600 cursor-pointer">
                                    Choose background image
                                </label>
                                <input type="file" name="background_image" id="background_image" accept="image/*" class="hidden">
                                <span id="background-filename" class="ml-3 text-sm text-gray-500">No file chosen</span>
                                <p class="mt-1 text-xs text-gray-500">Or upload your own. Recommended 1920x1080px.</p>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Advertisement --}}
                <div class="border border-gray-200 rounded-lg p-6">
                    <h3 class="text-base font-semibold text-gray-900">Advertisement</h3>
                    <p class="mt-1 text-sm text-gray-500 mb-4">A recurring image on the right half of the screen.</p>

                    <div class="flex items-start gap-3">
                        <input type="hidden" name="advertisement_enabled" value="0">
                        <input id="advertisement_enabled" name="advertisement_enabled" type="checkbox" value="1" class="mt-0.5 {{ $checkClass }}" {{ $bool('advertisement_enabled') ? 'checked' : '' }}>
                        <label for="advertisement_enabled" class="text-sm text-gray-900">Enable advertisement rotation</label>
                        <x-info-tip text="Shows the image over the right half of the screen at a set interval. The room status stays visible on the left, and a tap dismisses the image early." />
                    </div>

                    @if(auth()->user()->hasAdvertisementFeature())
                        <div id="advertisement-settings" class="mt-4 {{ $bool('advertisement_enabled') ? '' : 'opacity-40 pointer-events-none' }}">
                            @if($profile && $val('advertisement_image'))
                                <div class="flex items-center space-x-4 mb-4">
                                    <img src="{{ route('profiles.images', ['profile' => $profile, 'type' => 'advertisement']) }}?v={{ $profile->updated_at->timestamp }}" alt="Current advertisement" class="h-16 w-24 object-cover border border-gray-300 rounded">
                                    <label class="inline-flex items-center text-sm text-red-600 hover:text-red-500 cursor-pointer">
                                        <input type="checkbox" name="remove_advertisement_image" value="1" class="mr-1">
                                        Remove image
                                    </label>
                                </div>
                            @endif
                            <div class="mb-4">
                                <label for="advertisement_image" class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-md text-white bg-oxford hover:bg-oxford-600 cursor-pointer">
                                    Choose advertisement image
                                </label>
                                <input type="file" name="advertisement_image" id="advertisement_image" accept="image/*" class="hidden">
                                <span id="advertisement-filename" class="ml-3 text-sm text-gray-500">No file chosen</span>
                                <p class="mt-1 text-xs text-gray-500">PNG, JPG or GIF, max 4 MB. Recommended 960x1080px (half-screen portrait).</p>
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label for="advertisement_interval" class="flex items-center gap-1.5 text-sm font-medium text-gray-700">
                                        Show every (minutes)
                                        <x-info-tip text="How often the image reappears. Every 5 minutes is noticeable; longer intervals are less intrusive in rooms that are in use all day." />
                                    </label>
                                    <input type="number" min="1" max="60" name="advertisement_interval" id="advertisement_interval" value="{{ $val('advertisement_interval', 5) }}" class="mt-1 {{ $narrowClass }}">
                                </div>
                                <div>
                                    <label for="advertisement_duration" class="flex items-center gap-1.5 text-sm font-medium text-gray-700">
                                        Display duration (seconds)
                                        <x-info-tip text="How long the image stays on screen before the status returns. The room status is partly covered during this time." />
                                    </label>
                                    <input type="number" min="5" max="300" name="advertisement_duration" id="advertisement_duration" value="{{ $val('advertisement_duration', 15) }}" class="mt-1 {{ $narrowClass }}">
                                </div>
                            </div>
                        </div>
                    @else
                        <p class="mt-3 text-xs text-gray-500">The advertisement image and timing are not available on your plan.</p>
                    @endif
                </div>

                <div class="flex items-center justify-end gap-x-6 pt-4 border-t border-gray-200">
                    <a href="{{ route('dashboard', ['tab' => 'profiles']) }}" class="text-sm font-semibold leading-6 text-gray-900">Cancel</a>
                    <button type="submit" class="rounded-md bg-blue-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600">
                        {{ $profile ? 'Update Profile' : 'Create Profile' }}
                    </button>
                </div>
            </div>
        </form>
    </x-cards.card>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Show the chosen filename next to each upload button.
        [['logo', 'logo-filename'], ['background_image', 'background-filename'], ['advertisement_image', 'advertisement-filename']]
            .forEach(([inputId, labelId]) => {
                const input = document.getElementById(inputId);
                const label = document.getElementById(labelId);
                if (!input || !label) return;
                input.addEventListener('change', function (e) {
                    label.textContent = e.target.files[0] ? e.target.files[0].name : 'No file chosen';
                });
            });

        // Dim the advertisement fields while the feature is switched off.
        const adToggle = document.getElementById('advertisement_enabled');
        const adPanel = document.getElementById('advertisement-settings');
        if (adToggle && adPanel) {
            adToggle.addEventListener('change', function () {
                adPanel.classList.toggle('opacity-40', !this.checked);
                adPanel.classList.toggle('pointer-events-none', !this.checked);
            });
        }
    });
</script>
@endpush
