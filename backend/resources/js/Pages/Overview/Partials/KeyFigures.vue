<script setup>
// Four figures that each say something worth knowing: how many displays run, how many rooms are
// free, what is still coming today, and whether every calendar can be read.
import { computed } from 'vue';
import Icon from '../../../Components/Icon.vue';

const props = defineProps({ figures: Object, accounts: Array });

const hm = (d) => `${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`;

// Every display that is not online is either offline or has no tablet, so the parts add up to the total.
function displaysSub(f) {
    if (f.withoutTablet === f.total) return 'No tablets paired yet';
    const parts = [];
    if (f.offline) parts.push(`${f.offline} offline`);
    if (f.withoutTablet) parts.push(`${f.withoutTablet} without a tablet`);
    return parts.length ? parts.join(', ') : 'All online';
}

function bookingsSub(f) {
    if (f.upcoming) return `${f.upcoming} still to come, next at ${hm(f.next)}`;
    return f.bookings ? 'All done for today' : 'Nothing booked today';
}

// Calendars as a health check rather than a count: connected and readable, or what is wrong.
const calendars = computed(() => {
    const failing = props.accounts.filter((a) => a.error).length;
    const unreadable = props.figures.unreadable;
    const n = props.accounts.length;
    if (!n) return { ok: null, value: '-', sub: 'None connected yet' };
    if (failing) return { ok: false, value: 'Action required', sub: `${failing === 1 ? '1 calendar needs' : `${failing} calendars need`} a new sign-in` };
    if (unreadable) return { ok: false, value: 'Not reachable', sub: `${unreadable} ${unreadable === 1 ? 'room' : 'rooms'} could not be read just now` };
    return { ok: true, value: 'All connected', sub: `${n} ${n === 1 ? 'calendar' : 'calendars'}, providers reachable` };
});

const tiles = computed(() => {
    const f = props.figures;
    return [
        { label: 'Displays online', value: f.online, of: f.total, sub: displaysSub(f) },
        { label: 'Rooms free now', value: f.free, of: f.live, sub: f.live ? `${f.live - f.free} in use` : 'Waiting for the tablets' },
        { label: 'Bookings today', value: f.bookings, sub: bookingsSub(f) },
    ];
});
</script>

<template>
    <section aria-label="Key figures" class="sp-card">
      <div class="grid grid-cols-2 py-1 lg:grid-cols-4">
        <div v-for="tile in tiles" :key="tile.label" class="flex flex-col gap-1 px-5 py-4 lg:border-r lg:border-line-soft">
            <span class="text-[13px] font-medium text-muted">{{ tile.label }}</span>
            <span class="flex items-baseline gap-1 font-display leading-none font-bold tabular-nums">
                <!-- Nothing to count yet reads as a dash, not as 0/0. -->
                <span class="text-[30px] tracking-[-0.03em]">{{ tile.of === 0 ? '-' : tile.value }}</span>
                <span v-if="tile.of" class="text-base text-muted">/ {{ tile.of }}</span>
            </span>
            <span class="text-xs text-muted">{{ tile.sub }}</span>
        </div>
        <div class="flex flex-col gap-1 px-5 py-4">
            <span class="text-[13px] font-medium text-muted">Calendars</span>
            <span class="flex min-h-[30px] items-center gap-2 font-display text-lg leading-tight font-bold"
                  :class="calendars.ok === false ? 'text-reserved-text' : calendars.ok ? 'text-available-text' : ''">
                <span v-if="calendars.ok !== null" class="grid h-6 w-6 flex-none place-items-center rounded-full"
                      :class="calendars.ok ? 'bg-available-bg' : 'bg-reserved-bg'">
                    <Icon :name="calendars.ok ? 'check' : 'alert'" :size="14" :stroke="2.5" />
                </span>
                {{ calendars.value }}
            </span>
            <span class="text-xs text-muted">{{ calendars.sub }}</span>
        </div>
      </div>
    </section>
</template>
