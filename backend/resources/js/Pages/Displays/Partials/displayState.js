// What a display row shows as its status: the room's state from useRoomDay, in the design
// system's status names (colour plus word), with a failed calendar sync as the one extra case.
const tones = { free: 'available', busy: 'reserved', checkin: 'checkin', offline: 'offline' };

// Full class names, so Tailwind sees them.
const text = { available: 'text-available-text', reserved: 'text-reserved-text', checkin: 'text-checkin-text', offline: 'text-ink-3' };
const fill = { available: 'bg-available', reserved: 'bg-reserved', checkin: 'bg-checkin', offline: 'bg-offline' };

export function displayState(display) {
    const state = display.state;
    let view;

    if (display.status === 'error') {
        view = { tone: 'checkin', label: 'Sync failed', detail: 'reconnect the calendar, then resume' };
    } else {
        view = { tone: tones[state.key] ?? 'offline', label: state.label, detail: state.detail || '' };
    }

    return { ...view, meeting: state.meeting || '', text: text[view.tone], fill: fill[view.tone] };
}
