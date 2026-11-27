<?php

namespace App\Support;

use App\Helpers\DisplaySettings;
use App\Helpers\DisplaySettingSections;
use App\Helpers\ProfileSettings;
use App\Models\Display;
use App\Models\DisplayProfile;
use App\Services\ImageService;

/**
 * What the display configuration page and the profile editor need to draw the same settings form:
 * the current values, the images, the choices and the section labels. Both pages render the same
 * Vue partials, so the values are shaped the same way for a display and for a profile.
 */
class DisplaySettingsForm
{
    public const FONTS = ['Inter', 'Roboto', 'Open Sans', 'Lato', 'Poppins', 'Montserrat'];

    /**
     * Resolved values of a display: its own value, else its profile's, else the built-in default.
     *
     * @return array<string, mixed>
     */
    public static function displayValues(Display $display): array
    {
        $ds = DisplaySettings::class;

        $values = [
            'check_in_enabled' => $ds::isCheckInEnabled($display),
            'check_in_minutes' => $ds::getCheckInMinutes($display),
            'check_in_grace_period' => $ds::getCheckInGracePeriod($display),
            'booking_enabled' => $ds::isBookingEnabled($display),
            'allow_future_bookings' => $ds::isFutureBookingEnabled($display),
            'extend_enabled' => $ds::isExtendEnabled($display),
            'cancel_permission' => $ds::getCancelPermission($display),
            'hide_admin_actions' => $ds::isAdminActionsHidden($display),

            'view_schedule' => $ds::isCalendarEnabled($display),
            'timeline_widget_mode' => $ds::getTimelineWidgetMode($display),
            'border_thickness' => $ds::getBorderThickness($display),
            'show_organizer' => $ds::isShowOrganizerEnabled($display),
            'show_meeting_title' => $ds::getShowMeetingTitle($display),
            'show_meeting_location' => $ds::getShowMeetingLocation($display),
            'all_day_events_block' => $ds::isAllDayEventsBlocking($display),
            'led_enabled' => $ds::isLedEnabled($display),

            'text_available' => $ds::getAvailableText($display) ?? '',
            'text_transitioning' => $ds::getTransitioningText($display) ?? '',
            'text_reserved' => $ds::getReservedText($display) ?? '',
            'text_checkin' => $ds::getCheckInText($display) ?? '',

            'theme' => $ds::getTheme($display),
            'font_family' => $ds::getFontFamily($display),

            'advertisement_enabled' => $ds::isAdvertisementEnabled($display),
            'advertisement_interval' => $ds::getAdvertisementInterval($display),
            'advertisement_duration' => $ds::getAdvertisementDuration($display),
        ];

        foreach (array_keys(DisplaySettings::FONT_SIZE_ELEMENTS) as $element) {
            $values["font_size_{$element}"] = $ds::getFontSize($display, $element);
        }

        return $values;
    }

    /**
     * A profile's stored values, with the same defaults the profile form always used for keys the
     * profile does not hold yet.
     *
     * @return array<string, mixed>
     */
    public static function profileValues(?DisplayProfile $profile): array
    {
        $stored = $profile ? ProfileSettings::all($profile) : [];
        $types = DisplaySettingSections::allProfileKeys();

        $defaults = [
            'check_in_enabled' => false,
            'check_in_minutes' => 15,
            'check_in_grace_period' => 5,
            'booking_enabled' => false,
            'allow_future_bookings' => false,
            'extend_enabled' => false,
            'cancel_permission' => 'all',
            'hide_admin_actions' => false,

            'view_schedule' => false,
            'timeline_widget_mode' => 'none',
            'border_thickness' => 'medium',
            'show_organizer' => false,
            'show_meeting_title' => true,
            'show_meeting_location' => false,
            'all_day_events_block' => true,
            'led_enabled' => false,

            'text_available' => '',
            'text_transitioning' => '',
            'text_reserved' => '',
            'text_checkin' => '',

            'theme' => 'classic',
            'font_family' => 'Inter',

            'advertisement_enabled' => false,
            'advertisement_interval' => 5,
            'advertisement_duration' => 15,
        ];

        foreach (array_keys(DisplaySettings::FONT_SIZE_ELEMENTS) as $element) {
            $defaults["font_size_{$element}"] = 'normal';
        }

        $values = [];
        foreach ($defaults as $key => $default) {
            if (! array_key_exists($key, $stored)) {
                $values[$key] = $default;

                continue;
            }

            // Values are stored encrypted as strings; give them back their type.
            $values[$key] = match ($types[$key] ?? 'string') {
                'boolean' => (bool) $stored[$key],
                'integer' => (int) $stored[$key],
                default => (string) $stored[$key],
            };
        }

        return $values;
    }

    /**
     * The display's logo, background and advertisement image as the form shows them. "own" tells
     * whether the image belongs to the display (removable here) or comes from its profile.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function displayImages(Display $display): array
    {
        $version = $display->updated_at?->timestamp;
        $url = fn (string $type) => route('displays.images', ['display' => $display, 'type' => $type]).'?v='.$version;

        $background = DisplaySettings::getBackgroundImage($display);
        $isDefault = $background && isset(ImageService::DEFAULT_BACKGROUNDS[$background]);
        $logo = DisplaySettings::getLogo($display);
        $ad = DisplaySettings::getAdvertisementImage($display);

        return [
            'logo' => [
                'url' => $logo ? $url('logo') : null,
                'own' => DisplaySettings::getOwnSetting($display, 'logo') !== null,
            ],
            'background' => [
                'url' => $background ? ($isDefault ? asset(ImageService::DEFAULT_BACKGROUNDS[$background]) : $url('background')) : null,
                'own' => DisplaySettings::getOwnSetting($display, 'background_image') !== null,
                'default' => $isDefault ? $background : null,
            ],
            'advertisement' => [
                'url' => $ad ? $url('advertisement') : null,
                'own' => DisplaySettings::getOwnSetting($display, 'advertisement_image') !== null,
            ],
        ];
    }

    /**
     * The profile's images. Everything on a profile is its own, so "own" is always true.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function profileImages(?DisplayProfile $profile): array
    {
        $none = ['url' => null, 'own' => false];

        if (! $profile) {
            return ['logo' => $none, 'background' => $none + ['default' => null], 'advertisement' => $none];
        }

        $version = $profile->updated_at?->timestamp;
        $url = fn (string $type) => route('profiles.images', ['profile' => $profile, 'type' => $type]).'?v='.$version;

        $background = ProfileSettings::get($profile, 'background_image');
        $isDefault = $background && isset(ImageService::DEFAULT_BACKGROUNDS[$background]);
        $logo = ProfileSettings::get($profile, 'logo');
        $ad = ProfileSettings::get($profile, 'advertisement_image');

        return [
            'logo' => ['url' => $logo ? $url('logo') : null, 'own' => (bool) $logo],
            'background' => [
                'url' => $background ? ($isDefault ? asset(ImageService::DEFAULT_BACKGROUNDS[$background]) : $url('background')) : null,
                'own' => (bool) $background,
                'default' => $isDefault ? $background : null,
            ],
            'advertisement' => ['url' => $ad ? $url('advertisement') : null, 'own' => (bool) $ad],
        ];
    }

    /**
     * The choices the form offers.
     *
     * @return array<string, mixed>
     */
    public static function options(): array
    {
        return [
            'fonts' => self::FONTS,
            'themes' => collect(DisplaySettings::THEMES)
                ->map(fn ($label, $value) => ['value' => $value, 'label' => $label, 'help' => DisplaySettings::THEME_DESCRIPTIONS[$value]])
                ->values()->all(),
            'fontSizeElements' => collect(DisplaySettings::FONT_SIZE_ELEMENTS)
                ->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values()->all(),
            'fontSizes' => collect(DisplaySettings::FONT_SIZES)
                ->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values()->all(),
            'backgrounds' => collect(ImageService::DEFAULT_BACKGROUNDS)
                ->map(fn ($path, $key) => ['key' => $key, 'url' => asset($path)])->values()->all(),
        ];
    }

    /**
     * Section labels and descriptions, keyed by section.
     *
     * @return array<string, array{label: string, description: string}>
     */
    public static function sections(): array
    {
        return collect(DisplaySettingSections::all())
            ->map(fn ($s) => ['label' => $s['label'], 'description' => $s['description']])
            ->all();
    }
}
