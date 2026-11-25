import { computed } from 'vue';

const hm = (date) => date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', hour12: false });

/**
 * One day's picture of every room, in the browser's own timezone: its status right now, its
 * bookings on a shared timeline and the figures the overview shows. `rooms` and `now` are refs;
 * `day` (a ref to a date, or null for today) picks another day, and `fullDay` draws all 24 hours
 * instead of office hours.
 */
export function useRoomDay(rooms, now, { day = null, fullDay = false } = {}) {
    const dayStart = computed(() => { const d = new Date(day?.value ?? now.value); d.setHours(0, 0, 0, 0); return d; });
    const isToday = computed(() => { const d = new Date(now.value); d.setHours(0, 0, 0, 0); return d.getTime() === dayStart.value.getTime(); });
    const dayEnd = computed(() => { const d = new Date(dayStart.value); d.setDate(d.getDate() + 1); return d; });

    // Today's bookings per room, as dates.
    const today = computed(() => rooms.value.map((room) => ({
        ...room,
        bookings: room.events
            .map((e) => ({ ...e, start: new Date(e.start), end: new Date(e.end), checkInUntil: e.checkInUntil ? new Date(e.checkInUntil) : null }))
            .filter((e) => e.end > dayStart.value && e.start < dayEnd.value),
    })));

    // 08:00 to 18:00, wider when a booking starts earlier or ends later.
    const range = computed(() => {
        if (fullDay) return { from: 0, to: 24 };
        let from = 8;
        let to = 18;
        for (const room of today.value) {
            for (const b of room.bookings) {
                const s = b.start < dayStart.value ? 0 : b.start.getHours();
                const e = b.end >= dayEnd.value ? 24 : b.end.getHours() + (b.end.getMinutes() ? 1 : 0);
                from = Math.min(from, s);
                to = Math.max(to, e);
            }
        }
        return { from, to };
    });

    const at = (hour) => { const d = new Date(dayStart.value); d.setHours(hour); return d; };
    const span = computed(() => at(range.value.to) - at(range.value.from));
    const percent = (date) => Math.min(100, Math.max(0, ((date - at(range.value.from)) / span.value) * 100));

    function status(room) {
        if (room.status === 'requires_pro') return { key: 'offline', label: 'Paused', detail: 'needs Pro' };
        if (room.status === 'deactivated') return { key: 'offline', label: 'Deactivated', detail: '' };
        if (room.device && !room.device.online) return { key: 'offline', label: 'Offline', detail: `last seen ${room.device.lastSeen}` };
        if (room.error) return { key: 'offline', label: 'Not loaded', detail: room.error };
        if (!room.hasData) return { key: 'offline', label: 'No data yet', detail: room.device ? 'waiting for the tablet' : 'no tablet paired' };
        if (!isToday.value) {
            const n = room.bookings.length;
            return { key: 'free', label: n ? `${n} ${n === 1 ? 'booking' : 'bookings'}` : 'No bookings', detail: '', meeting: '' };
        }

        const t = now.value;
        const current = room.bookings.find((b) => b.start <= t && b.end > t);
        if (current && current.checkInUntil && t < current.checkInUntil) {
            return { key: 'checkin', label: 'Waiting for check-in', detail: `until ${hm(current.checkInUntil)}`, meeting: current.title };
        }
        if (current) return { key: 'busy', label: 'In use', detail: `until ${hm(current.end)}`, meeting: current.title };

        const next = room.bookings.find((b) => b.start > t);
        return next
            ? { key: 'free', label: 'Free', detail: `until ${hm(next.start)}`, meeting: `Next: ${next.title}` }
            : { key: 'free', label: 'Free', detail: 'for the rest of the day', meeting: '' };
    }

    const list = computed(() => today.value.map((room) => {
        const s = status(room);
        // "Free until 11:00", "In use until 10:30", "Offline, last seen 5 minutes ago".
        s.summary = !s.detail ? s.label : ['free', 'busy', 'checkin'].includes(s.key) ? `${s.label} ${s.detail}` : `${s.label}, ${s.detail}`;
        return {
            ...room,
            state: s,
            blocks: room.bookings.map((b) => {
                const live = b.start <= now.value && b.end > now.value;
                const waiting = live && s.key === 'checkin';
                return {
                    ...b,
                    room: room.name,
                    roomUrl: room.url,
                    roomId: room.id,
                    actions: room.actions,
                    eventsUrl: room.urls?.events,
                    live,
                    time: `${hm(b.start)} to ${hm(b.end)}`,
                    minutes: Math.round((b.end - b.start) / 60000),
                    left: percent(b.start),
                    width: Math.max(0.6, percent(b.end) - percent(b.start)),
                    tone: b.end <= now.value ? 'past' : live ? (waiting ? 'checkin' : 'busy') : 'booked',
                };
            }),
        };
    }));

    // Every hour of the window with its place on the timeline, for the grid lines and labels.
    const hours = computed(() => {
        const out = [];
        for (let h = range.value.from; h <= range.value.to; h++) out.push({ label: `${String(h).padStart(2, '0')}:00`, left: percent(at(h)), hour: h });
        return out;
    });

    const figures = computed(() => {
        const rooms = list.value;
        const withDevice = rooms.filter((r) => r.device);
        const online = withDevice.filter((r) => r.device.online).length;
        const live = rooms.filter((r) => r.hasData && ['free', 'busy', 'checkin'].includes(r.state.key));
        const bookings = rooms.flatMap((r) => r.bookings);
        return {
            online, total: rooms.length, offline: withDevice.length - online, withoutTablet: rooms.length - withDevice.length,
            free: live.filter((r) => r.state.key === 'free').length, live: live.length,
            bookings: bookings.length, fromTablet: bookings.filter((b) => b.tablet).length,
            upcoming: bookings.filter((b) => b.start > now.value).length,
            next: bookings.filter((b) => b.start > now.value).sort((a, b) => a.start - b.start)[0]?.start ?? null,
            unreadable: rooms.filter((r) => r.error).length,
        };
    });

    // Share of the rooms in use per hour, from the rooms we have bookings for.
    const occupancy = computed(() => {
        const rooms = list.value.filter((r) => r.hasData);
        if (!rooms.length) return [];
        const out = [];
        for (let h = range.value.from; h < range.value.to; h++) {
            const from = at(h);
            const to = at(h + 1);
            const used = rooms.reduce((sum, r) => sum + r.bookings.reduce((m, b) => m + Math.max(0, Math.min(b.end, to) - Math.max(b.start, from)), 0) / 3600000, 0);
            out.push({
                hour: h,
                share: Math.min(1, used / rooms.length),
                when: to <= now.value ? 'past' : from <= now.value ? 'now' : 'later',
            });
        }
        return out;
    });

    // The now line only on today.
    const nowLeft = computed(() => (isToday.value ? percent(now.value) : null));

    // Where a click on the timeline lands, as a time on this day rounded down to the quarter hour.
    const timeAt = (fraction) => {
        const ms = at(range.value.from).getTime() + fraction * span.value;
        const quarter = 15 * 60000;
        return new Date(Math.floor(ms / quarter) * quarter);
    };

    return { list, hours, figures, occupancy, nowLeft, nowLabel: computed(() => hm(now.value)), isToday, dayStart, percent, timeAt };
}
