<?php

namespace App\Helpers;

/**
 * The single source of truth for how display settings are grouped.
 *
 * A display follows its linked profile *per section*: saving a section writes only that section's
 * keys, so changing the branding of one room never detaches its behaviour from the profile. Every
 * consumer derives from this definition — the display configuration screen, the profile form, the
 * "follows profile / own settings" badges, the per-section reset, the bulk assign action and the
 * copy-down when a profile is deleted.
 *
 * `profile_keys` are the keys a profile can own, mapped to the type used by
 * DisplaySettings::setSetting()/ProfileSettings::set(). Uploaded images are profile keys too: their
 * stored value is a path, so a display that inherits one simply resolves to the profile's file.
 */
class DisplaySettingSections
{
    public const BEHAVIOR = 'behavior';

    public const DISPLAY = 'display';

    public const TEXTS = 'texts';

    public const BRANDING = 'branding';

    public const ADVERTISEMENT = 'advertisement';

    /**
     * `legacy_keys` are renamed keys that may still sit on older displays. They are not written
     * anymore, but they do count as "own value" for the badge and are cleared on reset — otherwise a
     * legacy display would look like it follows its profile while it actually overrides it.
     *
     * @return array<string, array{
     *     label: string,
     *     description: string,
     *     profile_keys: array<string, string>,
     *     display_only_keys: array<int, string>,
     *     legacy_keys: array<int, string>
     * }>
     */
    public static function all(): array
    {
        return [
            self::BEHAVIOR => [
                'label' => 'Behavior',
                'description' => 'What people can do from the display: check in, book, extend and cancel.',
                'profile_keys' => [
                    'check_in_enabled' => 'boolean',
                    'check_in_minutes' => 'integer',
                    'check_in_grace_period' => 'integer',
                    'booking_enabled' => 'boolean',
                    'allow_future_bookings' => 'boolean',
                    'extend_enabled' => 'boolean',
                    'cancel_permission' => 'string',
                    'hide_admin_actions' => 'boolean',
                ],
                'display_only_keys' => [],
                'legacy_keys' => [],
            ],
            self::DISPLAY => [
                'label' => 'Display',
                'description' => 'What the display shows: schedule, timeline and meeting details.',
                'profile_keys' => [
                    'view_schedule' => 'boolean',
                    'timeline_widget_mode' => 'string',
                    'border_thickness' => 'string',
                    'show_organizer' => 'boolean',
                    'show_meeting_title' => 'boolean',
                    'show_room_name' => 'boolean',
                ],
                'display_only_keys' => [],
                // 'view_schedule' used to be 'calendar_enabled'; see DisplaySettings::isCalendarEnabled().
                'legacy_keys' => ['calendar_enabled'],
            ],
            self::TEXTS => [
                'label' => 'State texts',
                'description' => 'Custom wording per room state. Leave a field empty to use the default text.',
                'profile_keys' => [
                    'text_available' => 'string',
                    'text_transitioning' => 'string',
                    'text_reserved' => 'string',
                    'text_checkin' => 'string',
                ],
                'display_only_keys' => [],
                'legacy_keys' => [],
            ],
            self::BRANDING => [
                'label' => 'Branding',
                'description' => 'Font, logo and background.',
                'profile_keys' => [
                    'font_family' => 'string',
                    'logo' => 'string',
                    'background_image' => 'string',
                ],
                'display_only_keys' => [],
                'legacy_keys' => [],
            ],
            self::ADVERTISEMENT => [
                'label' => 'Advertisement',
                'description' => 'Advertisement rotation and the image that is shown.',
                'profile_keys' => [
                    'advertisement_enabled' => 'boolean',
                    'advertisement_interval' => 'integer',
                    'advertisement_duration' => 'integer',
                    'advertisement_image' => 'string',
                ],
                'display_only_keys' => [],
                'legacy_keys' => [],
            ],
        ];
    }

    public static function exists(string $section): bool
    {
        return array_key_exists($section, self::all());
    }

    /**
     * The keys of a section that a profile can own, as key => type.
     *
     * @return array<string, string>
     */
    public static function profileKeys(string $section): array
    {
        return self::all()[$section]['profile_keys'] ?? [];
    }

    /**
     * The keys that make a section count as "own settings": the profile-owned keys plus any legacy
     * keys that older displays may still carry.
     *
     * @return array<int, string>
     */
    public static function ownershipKeys(string $section): array
    {
        $definition = self::all()[$section] ?? null;

        if ($definition === null) {
            return [];
        }

        return array_merge(array_keys($definition['profile_keys']), $definition['legacy_keys']);
    }

    /**
     * Every profile-owned key across all sections, as key => type. Used by the profile form and by
     * the copy-down when a profile is deleted.
     *
     * @return array<string, string>
     */
    public static function allProfileKeys(): array
    {
        $keys = [];

        foreach (self::all() as $section) {
            $keys += $section['profile_keys'];
        }

        return $keys;
    }

    /**
     * The section a profile-owned key belongs to, or null when the key is not profile-owned.
     */
    public static function sectionForKey(string $key): ?string
    {
        foreach (self::all() as $section => $definition) {
            if (array_key_exists($key, $definition['profile_keys'])) {
                return $section;
            }
        }

        return null;
    }
}
