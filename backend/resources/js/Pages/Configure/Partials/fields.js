// Shared bits for the settings form, used by the display configuration page and the profile editor.

export const inputClass = 'w-full min-w-0 rounded-md border border-line bg-surface px-3 py-2 text-[13px] text-ink focus:border-navy focus:outline-none';
export const numberClass = 'w-[76px] rounded-md border border-line bg-surface px-3 py-2 text-[13px] text-ink focus:border-navy focus:outline-none';

// What the tablet shows when a state text is left empty (the app's built-in wording).
export const defaultTexts = {
    text_available: 'All yours!',
    text_transitioning: 'Make it quick!',
    text_reserved: 'Reserved',
    text_checkin: 'Check-in for meeting',
};

export const cancelOptions = [
    { value: 'all', label: 'Everyone, any meeting can be ended' },
    { value: 'tablet_only', label: 'Only meetings booked on the tablet' },
    { value: 'none', label: 'Nobody, ending is turned off' },
];

export const timelineOptions = [
    { value: 'none', label: 'Off', help: 'No timeline on the display' },
    { value: 'side_panel', label: 'Side panel', help: 'Slides in when someone taps the calendar icon' },
    { value: 'inline', label: 'Inline', help: 'Always visible next to the status' },
    { value: 'full_panel', label: 'Full height', help: 'A full-height timeline on the right' },
];

export const borderOptions = [
    { value: 'small', label: 'Small' },
    { value: 'medium', label: 'Medium' },
    { value: 'large', label: 'Large' },
];

// The keys each section saves, matching DisplaySettingSections and the controller's rules.
export const sectionKeys = {
    behavior: ['check_in_enabled', 'check_in_minutes', 'check_in_grace_period', 'booking_enabled', 'allow_future_bookings', 'extend_enabled', 'cancel_permission', 'hide_admin_actions'],
    display: ['view_schedule', 'timeline_widget_mode', 'border_thickness', 'show_organizer', 'show_meeting_title', 'show_meeting_location', 'all_day_events_block', 'led_enabled'],
    texts: ['text_available', 'text_transitioning', 'text_reserved', 'text_checkin'],
    branding: ['theme', 'font_family'], // plus a font_size_<element> per options.fontSizeElements
    advertisement: ['advertisement_enabled', 'advertisement_interval', 'advertisement_duration'],
};

// Fields that only travel with a save: uploads, removals and a picked bundled background.
export const uploadFields = {
    branding: { logo: null, remove_logo: false, background_image: null, remove_background_image: false, default_background: null },
    advertisement: { advertisement_image: null, remove_advertisement_image: false },
};

// The keys a section saves, including the font size per text element the server lists.
export function keysFor(section, options) {
    const keys = sectionKeys[section] || [];
    return section === 'branding' ? [...keys, ...options.fontSizeElements.map((e) => `font_size_${e.key}`)] : keys;
}
