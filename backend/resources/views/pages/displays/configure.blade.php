@extends('layouts.base')
@section('title', 'Configure ' . $display->name)
@section('container_class', 'max-w-3xl')

@php
    $ds = \App\Helpers\DisplaySettings::class;
    $inputClass = 'px-3 py-2 block w-full border rounded-md border-gray-300 focus:border-blue-500 focus:ring-blue-500 sm:text-sm';
    $narrowClass = 'px-3 py-2 block w-32 border rounded-md border-gray-300 focus:border-blue-500 focus:ring-blue-500 sm:text-sm';
    $checkClass = 'h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-600';
    $radioClass = 'h-4 w-4 border-gray-300 text-blue-600 focus:ring-blue-600';
@endphp

@section('content')
    <x-cards.card>
        <div class="sm:flex sm:items-center mb-6">
            <div class="sm:flex-auto">
                <h1 class="text-lg font-semibold leading-6 text-gray-900">Configure display</h1>
                <p class="mt-1 text-sm text-gray-500">Behaviour, texts and branding for "{{ $display->name }}"</p>
            </div>
            <div class="mt-4 sm:ml-16 sm:mt-0 sm:flex-none">
                <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-x-1.5 rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                    <x-icons.arrow-left class="h-4 w-4" />
                    Back to Dashboard
                </a>
            </div>
        </div>

        <x-alerts.alert :errors="$errors" />

        {{-- Profile link --}}
        <div class="mb-6 border border-gray-200 rounded-lg p-6 bg-gray-50">
            <div class="mb-4">
                <h3 class="text-base font-semibold text-gray-900">Profile</h3>
                <p class="mt-1 text-sm text-gray-500">
                    A profile holds the same sections as below. Every section either follows the profile or has
                    its own values for this display, saving a section is what detaches that one section.
                </p>
            </div>
            <form action="{{ route('displays.profile.update', $display) }}" method="POST" class="flex items-end gap-3">
                @csrf
                @method('PUT')
                <div class="flex-auto">
                    <label for="display_profile_id" class="block text-sm font-medium text-gray-700 mb-1">Linked profile</label>
                    <select name="display_profile_id" id="display_profile_id" class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                        <option value="">No profile</option>
                        @foreach($profiles as $profile)
                            <option value="{{ $profile->id }}" {{ $display->display_profile_id === $profile->id ? 'selected' : '' }}>{{ $profile->name }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">Save link</button>
            </form>
            @if($display->display_profile_id)
                <form action="{{ route('displays.settings.reset-to-profile', $display) }}" method="POST" class="mt-3"
                      onsubmit="return confirm('Reset every section to follow the profile? All settings customised on this display will be removed.');">
                    @csrf
                    <button type="submit" class="text-sm font-medium text-red-600 hover:text-red-500">Reset all sections to profile</button>
                </form>
            @endif
        </div>

        <div class="space-y-6">
            {{-- Behavior --}}
            <x-displays.section-card :display="$display"
                                     :section="\App\Helpers\DisplaySettingSections::BEHAVIOR"
                                     :label="$sections[\App\Helpers\DisplaySettingSections::BEHAVIOR]['label']"
                                     :description="$sections[\App\Helpers\DisplaySettingSections::BEHAVIOR]['description']">
                <div class="space-y-5">
                    <label class="flex items-start gap-3 cursor-pointer">
                        <input type="checkbox" name="check_in_enabled" value="1" id="check_in_enabled" class="mt-0.5 {{ $checkClass }}"
                               {{ $ds::isCheckInEnabled($display) ? 'checked' : '' }}>
                        <div>
                            <span class="flex items-center gap-1.5 text-sm font-medium text-gray-900">Require check-in
                                <x-info-tip text="Attendees must confirm on the display that the meeting is happening. If nobody checks in within the grace period, the room is released and shows as available again — so no-show bookings stop blocking the room." />
                            </span>
                            <p class="text-xs text-gray-500">Attendees confirm the meeting on the display; no check-in releases the room.</p>
                        </div>
                    </label>

                    <div id="checkInTiming" class="grid grid-cols-2 gap-4 pl-7" style="display: {{ $ds::isCheckInEnabled($display) ? 'grid' : 'none' }};">
                        <div>
                            <label for="check_in_minutes" class="block text-sm font-medium text-gray-700">Check-in window (minutes before)</label>
                            <input type="number" min="1" max="60" name="check_in_minutes" id="check_in_minutes"
                                   value="{{ old('check_in_minutes', $ds::getCheckInMinutes($display)) }}" class="mt-1 {{ $narrowClass }}">
                            <p class="mt-1 text-xs text-gray-500">Default: 15 minutes.</p>
                        </div>
                        <div>
                            <label for="check_in_grace_period" class="block text-sm font-medium text-gray-700">Grace period (minutes after)</label>
                            <input type="number" min="1" max="30" name="check_in_grace_period" id="check_in_grace_period"
                                   value="{{ old('check_in_grace_period', $ds::getCheckInGracePeriod($display)) }}" class="mt-1 {{ $narrowClass }}">
                            <p class="mt-1 text-xs text-gray-500">Default: 5 minutes.</p>
                        </div>
                    </div>

                    <label class="flex items-start gap-3 cursor-pointer">
                        <input type="checkbox" name="booking_enabled" value="1" id="booking_enabled" class="mt-0.5 {{ $checkClass }}"
                               {{ $ds::isBookingEnabled($display) ? 'checked' : '' }}>
                        <div>
                            <span class="flex items-center gap-1.5 text-sm font-medium text-gray-900">Allow booking from the display
                                <x-info-tip text="Bookings are written back to the connected calendar, which requires write permission on that account — with read-only access the booking will fail." />
                            </span>
                            <p class="text-xs text-gray-500">Users can reserve the room straight from the tablet.</p>
                        </div>
                    </label>

                    <div id="futureBooking" class="pl-7" style="display: {{ $ds::isBookingEnabled($display) ? 'block' : 'none' }};">
                        <label class="flex items-start gap-3 cursor-pointer">
                            <input type="checkbox" name="allow_future_bookings" value="1" class="mt-0.5 {{ $checkClass }}"
                                   {{ $ds::isFutureBookingEnabled($display) ? 'checked' : '' }}>
                            <div>
                                <span class="text-sm font-medium text-gray-900">Allow future bookings</span>
                                <p class="text-xs text-gray-500">Book days other than today.</p>
                            </div>
                        </label>
                    </div>

                    <label class="flex items-start gap-3 cursor-pointer">
                        <input type="checkbox" name="extend_enabled" value="1" class="mt-0.5 {{ $checkClass }}"
                               {{ $ds::isExtendEnabled($display) ? 'checked' : '' }}>
                        <div>
                            <span class="flex items-center gap-1.5 text-sm font-medium text-gray-900">Allow extending a meeting
                                <x-info-tip text="Adds +15/+30/+60 minute buttons during a meeting. Extending fails when the next booking would overlap, and external events need write permission." />
                            </span>
                            <p class="text-xs text-gray-500">+15, +30 or +60 minutes.</p>
                        </div>
                    </label>

                    <label class="flex items-start gap-3 cursor-pointer">
                        <input type="checkbox" name="hide_admin_actions" value="1" class="mt-0.5 {{ $checkClass }}"
                               {{ $ds::isAdminActionsHidden($display) ? 'checked' : '' }}>
                        <div>
                            <span class="flex items-center gap-1.5 text-sm font-medium text-gray-900">Hide admin actions
                                <x-info-tip text="Stops visitors reconfiguring a public tablet. Admins still reach the buttons by long-pressing the room name; they then show for 30 seconds." />
                            </span>
                            <p class="text-xs text-gray-500">Hides switch-room and logout.</p>
                        </div>
                    </label>

                    <div class="pt-4 border-t border-gray-100">
                        <p class="flex items-center gap-1.5 text-sm font-medium text-gray-700 mb-2">Who can cancel a meeting
                            <x-info-tip text="Controls the End meeting button. 'Tablet bookings only' lets people end what was booked on the tablet but never a meeting from someone's calendar — the safest choice for shared calendars." />
                        </p>
                        <div class="space-y-2">
                            @foreach(['all' => 'Everyone — any event can be cancelled (default)', 'tablet_only' => 'Tablet bookings only', 'none' => 'Nobody — cancelling is disabled'] as $value => $label)
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="radio" name="cancel_permission" value="{{ $value }}" class="{{ $radioClass }}"
                                           {{ $ds::getCancelPermission($display) === $value ? 'checked' : '' }}>
                                    <span class="text-sm text-gray-700">{{ $label }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>
            </x-displays.section-card>

            {{-- Display --}}
            <x-displays.section-card :display="$display"
                                     :section="\App\Helpers\DisplaySettingSections::DISPLAY"
                                     :label="$sections[\App\Helpers\DisplaySettingSections::DISPLAY]['label']"
                                     :description="$sections[\App\Helpers\DisplaySettingSections::DISPLAY]['description']">
                @php $mode = $ds::getTimelineWidgetMode($display); @endphp
                <div class="space-y-5">
                    <div>
                        <p class="flex items-center gap-1.5 text-sm font-medium text-gray-700 mb-2">Today's schedule
                            <x-info-tip text="A side panel keeps the status screen clean and opens on tap; inline and full-height keep the timeline permanently in view, which suits larger screens." />
                        </p>
                        <div class="space-y-2">
                            @foreach([
                                'none' => ['Disabled', 'No timeline shown on the display'],
                                'side_panel' => ['Side panel', 'Slides over when users tap the calendar icon'],
                                'inline' => ['Inline', 'Always visible next to the content, vertically centered'],
                                'full_panel' => ['Full-height panel', 'Full-height timeline on the right side'],
                            ] as $value => [$title, $hint])
                                <label class="flex items-start gap-3 cursor-pointer">
                                    <input type="radio" name="timeline_widget_mode" value="{{ $value }}" class="mt-0.5 {{ $radioClass }}"
                                           {{ $mode === $value ? 'checked' : '' }}>
                                    <div>
                                        <span class="text-sm font-medium text-gray-900">{{ $title }}</span>
                                        <p class="text-xs text-gray-500">{{ $hint }}</p>
                                    </div>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <label class="flex items-start gap-3 cursor-pointer pt-4 border-t border-gray-100">
                        <input type="checkbox" name="view_schedule" value="1" class="mt-0.5 {{ $checkClass }}"
                               {{ $ds::isCalendarEnabled($display) ? 'checked' : '' }}>
                        <div>
                            <span class="text-sm font-medium text-gray-900">View schedule button</span>
                            <p class="text-xs text-gray-500">A bottom-bar button that opens today's full schedule. Combines with the options above.</p>
                        </div>
                    </label>

                    <label class="flex items-start gap-3 cursor-pointer">
                        <input type="checkbox" name="show_organizer" value="1" class="mt-0.5 {{ $checkClass }}"
                               {{ $ds::isShowOrganizerEnabled($display) ? 'checked' : '' }}>
                        <div>
                            <span class="flex items-center gap-1.5 text-sm font-medium text-gray-900">Show the meeting organizer
                                <x-info-tip text="Shows who booked the meeting, taken from the calendar event. Consider leaving this off in public areas where names should not be readable by visitors." />
                            </span>
                            <p class="text-xs text-gray-500">Taken from the calendar event (Google, Microsoft or CalDAV).</p>
                        </div>
                    </label>

                    <label class="flex items-start gap-3 cursor-pointer">
                        <input type="checkbox" name="show_meeting_title" value="1" class="mt-0.5 {{ $checkClass }}"
                               {{ $ds::getShowMeetingTitle($display) ? 'checked' : '' }}>
                        <div>
                            <span class="flex items-center gap-1.5 text-sm font-medium text-gray-900">Show meeting titles
                                <x-info-tip text="When off, the title is replaced by the Reserved text from the State texts section. Useful when subjects can be confidential, such as '1-on-1' or a client name." />
                            </span>
                            <p class="text-xs text-gray-500">Uncheck to hide titles in privacy-sensitive environments.</p>
                        </div>
                    </label>

                    <div class="pt-4 border-t border-gray-100">
                        <p class="flex items-center gap-1.5 text-sm font-medium text-gray-700 mb-2">Border thickness
                            <x-info-tip text="Thickness of the coloured status border around the screen. Large reads clearly from a corridor; small suits a tablet you stand right in front of." />
                        </p>
                        <div class="space-y-2">
                            @foreach(['small' => 'Small — thin borders, minimalist', 'medium' => 'Medium — standard (default)', 'large' => 'Large — thick borders, better visibility'] as $value => $label)
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="radio" name="border_thickness" value="{{ $value }}" class="{{ $radioClass }}"
                                           {{ $ds::getBorderThickness($display) === $value ? 'checked' : '' }}>
                                    <span class="text-sm text-gray-700">{{ $label }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>
            </x-displays.section-card>

            {{-- State texts --}}
            <x-displays.section-card :display="$display"
                                     :section="\App\Helpers\DisplaySettingSections::TEXTS"
                                     :label="$sections[\App\Helpers\DisplaySettingSections::TEXTS]['label']"
                                     :description="$sections[\App\Helpers\DisplaySettingSections::TEXTS]['description']">
                <div class="grid grid-cols-2 gap-4">
                    @foreach([
                        'text_available' => ['Available', 'All yours!', 'getAvailableText'],
                        'text_transitioning' => ['Transitioning', 'Keep it short!', 'getTransitioningText'],
                        'text_reserved' => ['Reserved', 'Meeting', 'getReservedText'],
                        'text_checkin' => ['Check-in', 'Check in for meeting', 'getCheckInText'],
                    ] as $key => [$label, $placeholder, $getter])
                        <div>
                            <label for="{{ $key }}" class="block text-sm font-medium text-gray-700">{{ $label }}</label>
                            <input type="text" name="{{ $key }}" id="{{ $key }}" maxlength="64" placeholder="{{ $placeholder }}"
                                   value="{{ old($key, $ds::$getter($display)) }}" class="mt-1 {{ $inputClass }}">
                        </div>
                    @endforeach
                </div>
                <p class="mt-3 text-xs text-gray-500">
                    An empty field means no custom text: the display falls back to its profile's text, and to the
                    built-in default when the profile has none either.
                </p>
            </x-displays.section-card>

            {{-- Branding --}}
            <x-displays.section-card :display="$display"
                                     :section="\App\Helpers\DisplaySettingSections::BRANDING"
                                     :label="$sections[\App\Helpers\DisplaySettingSections::BRANDING]['label']"
                                     :description="$sections[\App\Helpers\DisplaySettingSections::BRANDING]['description']"
                                     :multipart="true">
                <div class="space-y-6">
                    <div>
                        <label for="font_family" class="block text-sm font-medium text-gray-700 mb-1">Font family</label>
                        <select name="font_family" id="font_family" class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                            @foreach(['Inter', 'Roboto', 'Open Sans', 'Lato', 'Poppins', 'Montserrat'] as $font)
                                <option value="{{ $font }}" {{ old('font_family', $ds::getFontFamily($display)) === $font ? 'selected' : '' }}>{{ $font }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Logo --}}
                    <div class="pt-4 border-t border-gray-100">
                        <label class="flex items-center gap-1.5 text-sm font-medium text-gray-700 mb-2">
                            Logo
                            <x-info-tip text="Uploading a logo here replaces the profile's logo for this display only, and detaches the Branding section from the profile." />
                        </label>
                        @php $ownLogo = $ds::getOwnSetting($display, 'logo'); @endphp
                        @if($ds::getLogo($display))
                            <div class="flex items-center space-x-4 mb-2">
                                <img src="{{ route('displays.images', ['display' => $display, 'type' => 'logo']) }}?v={{ $display->updated_at->timestamp }}" alt="Current logo" class="h-16 w-24 object-contain border border-gray-300 rounded">
                                @if($ownLogo)
                                    <label class="inline-flex items-center text-sm text-red-600 hover:text-red-500 cursor-pointer">
                                        <input type="checkbox" name="remove_logo" value="1" class="mr-1">
                                        Remove logo
                                    </label>
                                @else
                                    <span class="text-xs text-gray-500">From profile <span class="font-medium">{{ $display->profile->name }}</span>. Upload one here to use a different logo on this display.</span>
                                @endif
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
                            <x-info-tip text="Fills the screen behind the room status. Keep it low-contrast so the status text stays readable from a distance." />
                        </label>
                        @php
                            $currentBackground = $ds::getBackgroundImage($display);
                            $ownBackground = $ds::getOwnSetting($display, 'background_image');
                            $isDefaultBackground = $currentBackground && isset(\App\Services\ImageService::DEFAULT_BACKGROUNDS[$currentBackground]);
                        @endphp
                        @if($currentBackground)
                            <div class="flex items-center space-x-4 mb-4">
                                <img src="{{ route('displays.images', ['display' => $display, 'type' => 'background']) }}?v={{ $display->updated_at->timestamp }}" alt="Current background" class="h-16 w-24 object-cover border border-gray-300 rounded">
                                @if($ownBackground)
                                    <label class="inline-flex items-center text-sm text-red-600 hover:text-red-500 cursor-pointer">
                                        <input type="checkbox" name="remove_background_image" value="1" class="mr-1">
                                        Remove background
                                    </label>
                                @else
                                    <span class="text-xs text-gray-500">From profile <span class="font-medium">{{ $display->profile->name }}</span>. Pick or upload one here to override it.</span>
                                @endif
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
            </x-displays.section-card>

            {{-- Advertisement --}}
            @if(auth()->user()->hasAdvertisementFeature())
                <x-displays.section-card :display="$display"
                                         :section="\App\Helpers\DisplaySettingSections::ADVERTISEMENT"
                                         :label="$sections[\App\Helpers\DisplaySettingSections::ADVERTISEMENT]['label']"
                                         :description="$sections[\App\Helpers\DisplaySettingSections::ADVERTISEMENT]['description']"
                                         :multipart="true">
                    <label class="flex items-start gap-3 cursor-pointer">
                        <input type="checkbox" name="advertisement_enabled" value="1" id="advertisement_enabled" class="mt-0.5 {{ $checkClass }}"
                               {{ $ds::isAdvertisementEnabled($display) ? 'checked' : '' }}>
                        <div>
                            <span class="flex items-center gap-1.5 text-sm font-medium text-gray-900">Enable advertisement rotation
                                <x-info-tip text="Shows the image over the right half of the screen at a set interval. The room status stays visible on the left, and a tap dismisses the image early." />
                            </span>
                            <p class="text-xs text-gray-500">Shown on the right half of the screen at a set interval.</p>
                        </div>
                    </label>

                    <div id="advertisement-settings" class="mt-4 {{ $ds::isAdvertisementEnabled($display) ? '' : 'opacity-40 pointer-events-none' }}">
                        @php $ownAd = $ds::getOwnSetting($display, 'advertisement_image'); @endphp
                        @if($ds::getAdvertisementImage($display))
                            <div class="flex items-center space-x-4 mb-4">
                                <img src="{{ route('displays.images', ['display' => $display, 'type' => 'advertisement']) }}?v={{ $display->updated_at->timestamp }}" alt="Current advertisement" class="h-16 w-24 object-cover border border-gray-300 rounded">
                                @if($ownAd)
                                    <label class="inline-flex items-center text-sm text-red-600 hover:text-red-500 cursor-pointer">
                                        <input type="checkbox" name="remove_advertisement_image" value="1" class="mr-1">
                                        Remove image
                                    </label>
                                @else
                                    <span class="text-xs text-gray-500">From profile <span class="font-medium">{{ $display->profile->name }}</span>. Upload one here to override it.</span>
                                @endif
                            </div>
                        @endif
                        <div class="mb-4">
                            <label for="advertisement_image" class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-md text-white bg-oxford hover:bg-oxford-600 cursor-pointer">
                                Choose advertisement image
                            </label>
                            <input type="file" name="advertisement_image" id="advertisement_image" accept="image/*" class="hidden">
                            <span id="advertisement-filename" class="ml-3 text-sm text-gray-500">No file chosen</span>
                            <p class="mt-1 text-xs text-gray-500">PNG, JPG or GIF, max 4 MB. Recommended 960x1080px. </p>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label for="advertisement_interval" class="block text-sm font-medium text-gray-700">Show every (minutes)</label>
                                <input type="number" name="advertisement_interval" id="advertisement_interval" min="1" max="60"
                                       value="{{ old('advertisement_interval', $ds::getAdvertisementInterval($display)) }}" class="mt-1 {{ $narrowClass }}">
                                <p class="mt-1 text-xs text-gray-500">1–60. Default: 5.</p>
                            </div>
                            <div>
                                <label for="advertisement_duration" class="block text-sm font-medium text-gray-700">Display duration (seconds)</label>
                                <input type="number" name="advertisement_duration" id="advertisement_duration" min="5" max="300"
                                       value="{{ old('advertisement_duration', $ds::getAdvertisementDuration($display)) }}" class="mt-1 {{ $narrowClass }}">
                                <p class="mt-1 text-xs text-gray-500">5–300. Default: 15.</p>
                            </div>
                        </div>
                    </div>
                </x-displays.section-card>
            @endif

            {{-- Read-only reference --}}
            <div class="border border-gray-200 rounded-lg p-6 bg-gray-50">
                <h3 class="text-base font-semibold text-gray-900 mb-4">Display information</h3>
                <dl class="grid grid-cols-1 gap-x-4 gap-y-4 sm:grid-cols-2">
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Display name</dt>
                        <dd class="text-sm text-gray-900">{{ $display->display_name }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Calendar</dt>
                        <dd class="text-sm text-gray-900">{{ $display->calendar?->name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Status</dt>
                        <dd class="text-sm">
                            @if($display->status === \App\Enums\DisplayStatus::ACTIVE)
                                <span class="inline-flex items-center rounded-md bg-green-50 px-2 py-1 text-xs font-medium text-green-700 ring-1 ring-inset ring-green-600/20">Active</span>
                            @else
                                <span class="inline-flex items-center rounded-md bg-gray-50 px-2 py-1 text-xs font-medium text-gray-600 ring-1 ring-inset ring-gray-500/10">Inactive</span>
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Last sync</dt>
                        <dd class="text-sm text-gray-900">{{ $display->last_sync_at ? $display->last_sync_at->diffForHumans() : 'Never' }}</dd>
                    </div>
                </dl>
            </div>
        </div>
    </x-cards.card>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Reveal the timing fields only when the toggle they belong to is on.
        const reveal = (toggleId, targetId, shownDisplay) => {
            const toggle = document.getElementById(toggleId);
            const target = document.getElementById(targetId);
            if (!toggle || !target) return;
            toggle.addEventListener('change', function () {
                target.style.display = this.checked ? shownDisplay : 'none';
            });
        };
        reveal('check_in_enabled', 'checkInTiming', 'grid');
        reveal('booking_enabled', 'futureBooking', 'block');

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
