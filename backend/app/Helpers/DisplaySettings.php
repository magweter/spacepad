<?php

namespace App\Helpers;

use App\Models\Display;
use App\Models\DisplaySetting;

class DisplaySettings
{
    public static function getSetting(Display $display, string $key, mixed $default = null): mixed
    {
        // A setting stored directly on the display acts as an override and always wins.
        [$hasOwn, $ownValue] = self::resolveOwnSetting($display, $key);
        if ($hasOwn) {
            return $ownValue;
        }

        // Otherwise fall back to the linked profile (live link).
        [$hasProfile, $profileValue] = self::resolveProfileSetting($display, $key);
        if ($hasProfile) {
            return $profileValue;
        }

        return $default;
    }

    /**
     * The value stored on the display itself, ignoring anything inherited from its profile.
     *
     * Needed wherever inheriting is not good enough — deleting an uploaded file, for instance: the
     * resolved path may belong to the profile, and removing it there would strip the image from every
     * other display that follows the same profile.
     */
    public static function getOwnSetting(Display $display, string $key, mixed $default = null): mixed
    {
        [$hasOwn, $ownValue] = self::resolveOwnSetting($display, $key);

        return $hasOwn ? $ownValue : $default;
    }

    /**
     * Resolve a setting stored directly on the display.
     *
     * @return array{0: bool, 1: mixed} [found, value]
     */
    private static function resolveOwnSetting(Display $display, string $key): array
    {
        // If settings relationship is already loaded, use it to avoid N+1 queries
        if ($display->relationLoaded('settings')) {
            $setting = $display->settings->firstWhere('key', $key);
        } else {
            // Fallback to querying if relationship is not loaded (backward compatibility)
            $setting = DisplaySetting::where('display_id', $display->id)
                ->where('key', $key)
                ->first();
        }

        return $setting ? [true, $setting->value] : [false, null];
    }

    /**
     * Resolve a setting inherited from the display's linked profile.
     *
     * @return array{0: bool, 1: mixed} [found, value]
     */
    private static function resolveProfileSetting(Display $display, string $key): array
    {
        if (! $display->display_profile_id) {
            return [false, null];
        }

        $profile = $display->relationLoaded('profile')
            ? $display->profile
            : $display->profile()->with('settings')->first();

        if (! $profile) {
            return [false, null];
        }

        $setting = $profile->relationLoaded('settings')
            ? $profile->settings->firstWhere('key', $key)
            : $profile->settings()->where('key', $key)->first();

        return $setting ? [true, $setting->value] : [false, null];
    }

    public static function setSetting(Display $display, string $key, mixed $value, string $type = 'string'): bool
    {
        try {
            DisplaySetting::updateOrCreate(
                [
                    'display_id' => $display->id,
                    'key' => $key,
                ],
                [
                    'value' => $value,
                    'type' => $type,
                ]
            );

            return true;
        } catch (\Exception $e) {
            report($e);

            return false;
        }
    }

    public static function deleteSetting(Display $display, string $key): bool
    {
        try {
            return DisplaySetting::where('display_id', $display->id)
                ->where('key', $key)
                ->delete() > 0;
        } catch (\Exception $e) {
            report($e);

            return false;
        }
    }

    public static function getAllSettings(Display $display): array
    {
        // Start from the linked profile's settings (if any) as the base layer...
        $settings = self::getProfileSettingsMap($display);

        // ...then overlay the display's own settings, which act as overrides.
        if ($display->relationLoaded('settings')) {
            $own = $display->settings;
        } else {
            // Fallback to querying if relationship is not loaded (backward compatibility)
            $own = DisplaySetting::where('display_id', $display->id)->get();
        }

        foreach ($own as $setting) {
            $settings[$setting->key] = $setting->value;
        }

        return $settings;
    }

    /**
     * Whether a section still follows the linked profile.
     *
     * A section follows the profile when the display has no own value for any of that section's
     * profile-owned keys. Saving a section writes those keys, which is exactly what detaches it —
     * so this needs no extra column to track. Uploaded images are ignored here: they always live
     * on the display and would otherwise make a section look detached.
     *
     * Returns false when there is no profile at all; "follows profile" then has no meaning.
     */
    public static function sectionFollowsProfile(Display $display, string $section): bool
    {
        if (! $display->display_profile_id) {
            return false;
        }

        $keys = DisplaySettingSections::ownershipKeys($section);

        if ($keys === []) {
            return true;
        }

        if ($display->relationLoaded('settings')) {
            return ! $display->settings->contains(fn ($setting) => in_array($setting->key, $keys, true));
        }

        return ! DisplaySetting::where('display_id', $display->id)
            ->whereIn('key', $keys)
            ->exists();
    }

    /**
     * Whether the display deviates from its profile in at least one section. Drives the
     * "· customised" hint in the displays overview.
     */
    public static function deviatesFromProfile(Display $display): bool
    {
        if (! $display->display_profile_id) {
            return false;
        }

        foreach (array_keys(DisplaySettingSections::all()) as $section) {
            if (! self::sectionFollowsProfile($display, $section)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Drop the display's own values for one section so it follows its profile again. Uploaded
     * images are deliberately kept — they are not part of the profile.
     */
    public static function resetSection(Display $display, string $section): bool
    {
        $keys = DisplaySettingSections::ownershipKeys($section);

        if ($keys === []) {
            return true;
        }

        try {
            DisplaySetting::where('display_id', $display->id)
                ->whereIn('key', $keys)
                ->delete();

            return true;
        } catch (\Exception $e) {
            report($e);

            return false;
        }
    }

    /**
     * Build a key => value map of the settings inherited from the display's profile.
     */
    private static function getProfileSettingsMap(Display $display): array
    {
        if (! $display->display_profile_id) {
            return [];
        }

        $profile = $display->relationLoaded('profile')
            ? $display->profile
            : $display->profile()->with('settings')->first();

        if (! $profile) {
            return [];
        }

        return $profile->settings->mapWithKeys(function ($setting) {
            return [$setting->key => $setting->value];
        })->toArray();
    }

    // Convenience methods for common settings
    public static function isCheckInEnabled(Display $display): bool
    {
        return self::getSetting($display, 'check_in_enabled', false);
    }

    public static function setCheckInEnabled(Display $display, bool $enabled): bool
    {
        return self::setSetting($display, 'check_in_enabled', $enabled, 'boolean');
    }

    public static function isBookingEnabled(Display $display): bool
    {
        return self::getSetting($display, 'booking_enabled', false);
    }

    public static function setBookingEnabled(Display $display, bool $enabled): bool
    {
        return self::setSetting($display, 'booking_enabled', $enabled, 'boolean');
    }

    // Logo settings
    public static function getLogo(Display $display): ?string
    {
        return self::getSetting($display, 'logo');
    }

    public static function setLogo(Display $display, string $logoPath): bool
    {
        return self::setSetting($display, 'logo', $logoPath, 'string');
    }

    public static function removeLogo(Display $display): bool
    {
        return self::deleteSetting($display, 'logo');
    }

    // Background image settings
    public static function getBackgroundImage(Display $display): ?string
    {
        return self::getSetting($display, 'background_image');
    }

    public static function setBackgroundImage(Display $display, string $backgroundPath): bool
    {
        return self::setSetting($display, 'background_image', $backgroundPath, 'string');
    }

    public static function removeBackgroundImage(Display $display): bool
    {
        return self::deleteSetting($display, 'background_image');
    }

    // Font family settings
    public static function getFontFamily(Display $display): string
    {
        return self::getSetting($display, 'font_family', 'Inter');
    }

    public static function setFontFamily(Display $display, string $fontFamily): bool
    {
        return self::setSetting($display, 'font_family', $fontFamily, 'string');
    }

    public static function getCheckInMinutes(Display $display): int
    {
        return self::getSetting($display, 'check_in_minutes', 15);
    }

    public static function setCheckInMinutes(Display $display, int $minutes): bool
    {
        return self::setSetting($display, 'check_in_minutes', $minutes, 'integer');
    }

    public static function getCheckInGracePeriod(Display $display): int
    {
        return self::getSetting($display, 'check_in_grace_period', 5);
    }

    public static function setCheckInGracePeriod(Display $display, int $minutes): bool
    {
        return self::setSetting($display, 'check_in_grace_period', $minutes, 'integer');
    }

    public static function isCalendarEnabled(Display $display): bool
    {
        // 'calendar_enabled' was the key before it was renamed to 'view_schedule'
        return self::getSetting($display, 'view_schedule', false)
            || self::getSetting($display, 'calendar_enabled', false)
            || self::getSetting($display, 'timeline_widget_mode', null) === 'view_schedule';
    }

    public static function setCalendarEnabled(Display $display, bool $enabled): bool
    {
        // Clear legacy keys so they can't override the new value
        if (! $enabled) {
            self::deleteSetting($display, 'calendar_enabled');
            // timeline_widget_mode = 'view_schedule' was an old way to enable this feature
            if (self::getSetting($display, 'timeline_widget_mode', null) === 'view_schedule') {
                self::deleteSetting($display, 'timeline_widget_mode');
            }
        }

        return self::setSetting($display, 'view_schedule', $enabled, 'boolean');
    }

    public static function isTimelineWidgetEnabled(Display $display): bool
    {
        return self::getTimelineWidgetMode($display) !== 'none';
    }

    public static function setTimelineWidgetEnabled(Display $display, bool $enabled): bool
    {
        // Legacy shim — maps boolean to mode
        return self::setTimelineWidgetMode($display, $enabled ? 'side_panel' : 'none');
    }

    public static function getTimelineWidgetMode(Display $display): string
    {
        $mode = self::getSetting($display, 'timeline_widget_mode', null);
        if ($mode !== null && $mode !== 'view_schedule') {
            return $mode;
        }
        // view_schedule was a former mode value — treat as no timeline widget
        if ($mode === 'view_schedule') {
            return 'none';
        }
        // Backward compat: migrate old boolean setting
        $legacy = self::getSetting($display, 'timeline_widget_enabled', false);

        return $legacy ? 'side_panel' : 'none';
    }

    public static function setTimelineWidgetMode(Display $display, string $mode): bool
    {
        return self::setSetting($display, 'timeline_widget_mode', $mode, 'string');
    }

    public static function isFutureBookingEnabled(Display $display): bool
    {
        return self::getSetting($display, 'allow_future_bookings', false);
    }

    public static function setFutureBookingEnabled(Display $display, bool $enabled): bool
    {
        return self::setSetting($display, 'allow_future_bookings', $enabled, 'boolean');
    }

    // Customizable display state texts (shorter keys)
    public static function getAvailableText(Display $display): ?string
    {
        return self::getSetting($display, 'text_available');
    }

    public static function setAvailableText(Display $display, string $text): bool
    {
        return self::setSetting($display, 'text_available', $text, 'string');
    }

    public static function getTransitioningText(Display $display): ?string
    {
        return self::getSetting($display, 'text_transitioning');
    }

    public static function setTransitioningText(Display $display, string $text): bool
    {
        return self::setSetting($display, 'text_transitioning', $text, 'string');
    }

    public static function getReservedText(Display $display): ?string
    {
        return self::getSetting($display, 'text_reserved');
    }

    public static function setReservedText(Display $display, string $text): bool
    {
        return self::setSetting($display, 'text_reserved', $text, 'string');
    }

    public static function getCheckInText(Display $display): ?string
    {
        return self::getSetting($display, 'text_checkin');
    }

    public static function setCheckInText(Display $display, string $text): bool
    {
        return self::setSetting($display, 'text_checkin', $text, 'string');
    }

    // Toggle for showing meeting title
    public static function getShowMeetingTitle(Display $display): bool
    {
        return self::getSetting($display, 'show_meeting_title', true);
    }

    public static function setShowMeetingTitle(Display $display, bool $show): bool
    {
        return self::setSetting($display, 'show_meeting_title', $show, 'boolean');
    }

    // Admin actions visibility
    public static function isAdminActionsHidden(Display $display): bool
    {
        return self::getSetting($display, 'hide_admin_actions', false);
    }

    public static function setAdminActionsHidden(Display $display, bool $hidden): bool
    {
        return self::setSetting($display, 'hide_admin_actions', $hidden, 'boolean');
    }

    // Cancel permission settings
    // Values: 'all' (default), 'tablet_only', 'none'
    public static function getCancelPermission(Display $display): string
    {
        return self::getSetting($display, 'cancel_permission', 'all');
    }

    public static function setCancelPermission(Display $display, string $permission): bool
    {
        if (! in_array($permission, ['all', 'tablet_only', 'none'])) {
            return false;
        }

        return self::setSetting($display, 'cancel_permission', $permission, 'string');
    }

    // Border thickness settings
    // Values: 'small', 'medium' (default), 'large'
    public static function getBorderThickness(Display $display): string
    {
        return self::getSetting($display, 'border_thickness', 'medium');
    }

    public static function setBorderThickness(Display $display, string $thickness): bool
    {
        if (! in_array($thickness, ['small', 'medium', 'large'])) {
            return false;
        }

        return self::setSetting($display, 'border_thickness', $thickness, 'string');
    }

    // Advertisement enabled toggle
    public static function isAdvertisementEnabled(Display $display): bool
    {
        return self::getSetting($display, 'advertisement_enabled', false);
    }

    public static function setAdvertisementEnabled(Display $display, bool $enabled): bool
    {
        return self::setSetting($display, 'advertisement_enabled', $enabled, 'boolean');
    }

    // Advertisement image settings
    public static function getAdvertisementImage(Display $display): ?string
    {
        return self::getSetting($display, 'advertisement_image');
    }

    public static function setAdvertisementImage(Display $display, string $path): bool
    {
        return self::setSetting($display, 'advertisement_image', $path, 'string');
    }

    public static function removeAdvertisementImage(Display $display): bool
    {
        return self::deleteSetting($display, 'advertisement_image');
    }

    // Advertisement interval in minutes (default 5)
    public static function getAdvertisementInterval(Display $display): int
    {
        return self::getSetting($display, 'advertisement_interval', 5);
    }

    public static function setAdvertisementInterval(Display $display, int $minutes): bool
    {
        return self::setSetting($display, 'advertisement_interval', $minutes, 'integer');
    }

    // Advertisement display duration in seconds (default 15)
    public static function getAdvertisementDuration(Display $display): int
    {
        return self::getSetting($display, 'advertisement_duration', 15);
    }

    public static function setAdvertisementDuration(Display $display, int $seconds): bool
    {
        return self::setSetting($display, 'advertisement_duration', $seconds, 'integer');
    }

    // Extend meeting enabled toggle
    public static function isExtendEnabled(Display $display): bool
    {
        return self::getSetting($display, 'extend_enabled', false);
    }

    public static function setExtendEnabled(Display $display, bool $enabled): bool
    {
        return self::setSetting($display, 'extend_enabled', $enabled, 'boolean');
    }

    // Show organizer toggle
    public static function isShowOrganizerEnabled(Display $display): bool
    {
        return self::getSetting($display, 'show_organizer', false);
    }

    public static function setShowOrganizerEnabled(Display $display, bool $enabled): bool
    {
        return self::setSetting($display, 'show_organizer', $enabled, 'boolean');
    }

    /**
     * Whether the day schedule prints the location line of each meeting.
     *
     * Off by default. A display hangs beside the room it lists and inside the building it names,
     * so the line mostly repeats what whoever is reading it is already standing in.
     */
    public static function getShowMeetingLocation(Display $display): bool
    {
        return self::getSetting($display, 'show_meeting_location', false);
    }

    public static function setShowMeetingLocation(Display $display, bool $show): bool
    {
        return self::setSetting($display, 'show_meeting_location', $show, 'boolean');
    }
}
