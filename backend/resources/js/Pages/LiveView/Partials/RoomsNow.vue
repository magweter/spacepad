<script setup>
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import { useRememberedRef } from '../../../Composables/useRememberedRef';
import MeetingDetails from './MeetingDetails.vue';
import BookMeeting from './BookMeeting.vue';
import Icon from '../../../Components/Icon.vue';
import Card from '../../../DesignSystem/Card.vue';
import EmptyState from '../../../DesignSystem/EmptyState.vue';
import Button from '../../../DesignSystem/Button.vue';
import { toast } from '../../../Components/Toasts/toasts';

const props = defineProps({
    rooms: Array, hours: Array, nowLeft: { type: Number, default: null }, nowLabel: String, urls: Object,
    day: Date, isToday: Boolean, loading: Boolean, percent: Function, timeAt: Function, now: Date,
});
const emit = defineEmits(['day', 'changed']);

const view = useRememberedRef('overview.rooms.view', 'timeline', ['timeline', 'cards']);
const rail = { free: 'bg-available', busy: 'bg-reserved', checkin: 'bg-checkin', offline: 'bg-offline' };
const rule = { free: 'sp-card-rule-available', busy: 'sp-card-rule-reserved', checkin: 'sp-card-rule-checkin', offline: 'sp-card-rule-offline' };
// Calendar blocks: a light fill with a coloured edge, so the title stays readable in every state.
const block = {
    past: 'border-offline bg-surface-raised text-muted',
    busy: 'border-reserved bg-reserved-bg text-reserved-text',
    checkin: 'border-checkin bg-checkin-bg text-checkin-text',
    booked: 'border-navy bg-navy-50 text-navy',
};
const open = ref(null);
const booking = ref(null);

// The whole day scrolls sideways. It opens on the current time (now about a third in), and on
// another day at 08:00.
const scroller = ref(null);
const roomColumn = () => (window.matchMedia('(min-width: 640px)').matches ? 190 : 170);
async function scrollToStart() {
    await nextTick();
    const el = scroller.value;
    if (!el) return;
    const grid = el.scrollWidth - roomColumn() - 20;
    const at = props.nowLeft ?? (8 / 24) * 100;
    const lead = props.nowLeft === null ? 0 : (el.clientWidth - roomColumn()) / 3;
    el.scrollLeft = Math.max(0, (at / 100) * grid - lead);
}
onMounted(scrollToStart);
watch(view, (v) => { if (v === 'timeline') scrollToStart(); });
watch(() => props.day?.getTime(), scrollToStart);

// Moving between days.
const pad = (n) => String(n).padStart(2, '0');
const dayValue = computed(() => (props.day ? `${props.day.getFullYear()}-${pad(props.day.getMonth() + 1)}-${pad(props.day.getDate())}` : ''));
const heading = computed(() => {
    if (props.isToday) return 'Today';
    const today = new Date(props.now); today.setHours(0, 0, 0, 0);
    const diff = Math.round((props.day - today) / 86400000);
    if (diff === 1) return 'Tomorrow';
    if (diff === -1) return 'Yesterday';
    return props.day.toLocaleDateString('en-GB', { weekday: 'long', day: 'numeric', month: 'long' });
});
function step(days) {
    const d = new Date(props.day);
    d.setDate(d.getDate() + days);
    emit('day', d);
}
function pickDate(e) {
    if (!e.target.value) return;
    const [y, m, d] = e.target.value.split('-').map(Number);
    emit('day', new Date(y, m - 1, d));
}

// Booking: a click on an empty spot of a room's row opens the form with that room and time. While
// hovering, a faint half-hour block shows where the booking would go.
const bookable = computed(() => props.rooms.filter((r) => r.actions?.book && r.urls?.book));
// The overview's quick actions open the same booking dialog.
defineExpose({ book: () => { booking.value = { roomId: null, start: props.isToday ? null : new Date(props.day.getTime() + 9 * 3600000) }; }, canBook: computed(() => bookable.value.length > 0) });
const hover = ref(null);
function slotAt(room, e) {
    if (!room.actions?.book) return null;
    const rect = e.currentTarget.getBoundingClientRect();
    let start = props.timeAt((e.clientX - rect.left) / rect.width);
    const end = new Date(start.getTime() + 30 * 60000);
    if (end <= props.now) return null;
    if (start < props.now) start = new Date(Math.floor(props.now.getTime() / 60000) * 60000);
    if (!room.actions.bookAhead && start > new Date(props.now.getTime() + 5 * 60000)) return null;
    return { start, left: props.percent(start), width: props.percent(end) - props.percent(start) };
}
function onMove(room, e) {
    if (e.target !== e.currentTarget && !e.target.dataset.grid) { hover.value = null; return; }
    const slot = slotAt(room, e);
    hover.value = slot ? { roomId: room.id, ...slot } : null;
}
function onPick(room, e) {
    if (e.target !== e.currentTarget && !e.target.dataset.grid) return;
    const slot = slotAt(room, e);
    if (slot) booking.value = { roomId: room.id, start: slot.start };
}

function done(message) {
    open.value = null;
    booking.value = null;
    if (message) toast({ title: message, tone: 'success' });
    emit('changed');
}
</script>

<template>
    <section aria-label="Live room status" class="flex flex-col gap-3.5">
        <div class="flex flex-wrap items-center gap-3">
            <h2 class="font-display text-base font-bold tracking-tight">{{ view === 'timeline' ? heading : 'Right now' }}</h2>
            <div v-if="rooms.length && view === 'timeline'" class="flex items-center gap-1">
                <button type="button" aria-label="Previous day" class="grid h-7 w-7 place-items-center rounded-md border border-line bg-surface text-ink-3 hover:bg-surface-raised" @click="step(-1)">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6" /></svg>
                </button>
                <button type="button" :disabled="isToday" class="h-7 rounded-md border border-line bg-surface px-2.5 text-xs font-semibold text-ink-3 hover:bg-surface-raised disabled:cursor-default disabled:opacity-50" @click="emit('day', null)">Today</button>
                <button type="button" aria-label="Next day" class="grid h-7 w-7 place-items-center rounded-md border border-line bg-surface text-ink-3 hover:bg-surface-raised" @click="step(1)">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 18 6-6-6-6" /></svg>
                </button>
                <label class="relative grid h-7 w-7 cursor-pointer place-items-center rounded-md border border-line bg-surface text-ink-3 hover:bg-surface-raised" title="Pick a date">
                    <Icon name="calendars" :size="14" />
                    <input type="date" :value="dayValue" aria-label="Pick a date" class="absolute inset-0 cursor-pointer opacity-0" @change="pickDate" @click="$event.target.showPicker?.()" />
                </label>
                <span v-if="loading" class="ml-1 inline-flex items-center gap-1.5 text-xs text-muted" role="status">
                    <span class="h-3.5 w-3.5 animate-spin rounded-full border-2 border-line border-t-navy"></span>Loading
                </span>
            </div>
            <div class="flex-1"></div>
            <button v-if="bookable.length" type="button" class="inline-flex h-7 items-center gap-1 rounded-md border border-line bg-surface px-2.5 text-xs font-semibold text-navy hover:bg-surface-raised"
                    @click="booking = { roomId: null, start: isToday ? null : new Date(day.getTime() + 9 * 3600000) }"><Icon name="plus" :size="13" :stroke="2.5" />Book a room</button>
            <div v-if="rooms.length" class="inline-flex overflow-hidden rounded-lg border border-line bg-surface">
                <button v-for="v in ['timeline', 'cards']" :key="v" type="button" :aria-pressed="view === v" @click="view = v"
                        class="px-3 py-1.5 text-xs font-semibold capitalize" :class="view === v ? 'bg-navy text-white' : 'text-ink-3 hover:bg-surface-raised'">{{ v }}</button>
            </div>
        </div>

        <EmptyState v-if="!rooms.length" title="No displays yet">Set up a display and its room shows up here, live.
            <template #actions><Button :href="urls.newDisplay" external>Set up a display</Button></template>
        </EmptyState>

        <!-- Timeline as a day calendar: an hour grid per room, each meeting a block that opens its details. -->
        <Card v-else-if="view === 'timeline'" padding="none" class="relative overflow-hidden">
         <div class="min-w-0 transition-opacity" :class="loading ? 'opacity-50' : ''" :aria-busy="loading">
          <div ref="scroller" class="overflow-x-auto">
            <!-- About 110 px per hour, so a half-hour meeting still shows its title; scrolls sideways. -->
            <div :style="{ minWidth: `${190 + (hours.length - 1) * 110}px` }">
                <div class="grid grid-cols-[170px_1fr] border-b border-line sm:grid-cols-[190px_1fr]">
                    <span class="sticky left-0 z-30 bg-surface px-5 py-2.5 text-[11px] font-semibold tracking-[0.04em] text-muted uppercase">Room</span>
                    <div class="relative mr-5 h-[34px] text-[11px] text-muted tabular-nums">
                        <span v-for="(h, i) in hours" :key="h.label" class="absolute top-2.5 -translate-x-1/2"
                              :class="i === 0 ? 'translate-x-0' : i === hours.length - 1 ? '-translate-x-full' : ''" :style="{ left: `${h.left}%` }">{{ h.label }}</span>
                        <span v-if="nowLeft !== null" class="absolute top-1.5 z-10 -translate-x-1/2 rounded-full bg-reserved px-1.5 py-0.5 text-[10px] font-semibold text-white tabular-nums" :style="{ left: `${nowLeft}%` }">{{ nowLabel }}</span>
                    </div>
                </div>

                <div v-for="room in rooms" :key="room.id" class="grid grid-cols-[170px_1fr] border-b border-b-line-soft sm:grid-cols-[190px_1fr]">
                    <!-- The status bar lives in the sticky room cell, so it stays in view while the day scrolls. -->
                    <a :href="room.url" class="sticky left-0 z-30 flex min-w-0 flex-col justify-center border-r border-line-soft bg-surface py-3 pr-3 pl-5 text-ink hover:bg-surface-raised">
                        <span class="absolute inset-y-0 left-0 w-1" :class="rail[room.state.key]" aria-hidden="true"></span>
                        <span class="truncate text-[13px] font-semibold">{{ room.name }}</span>
                        <span class="truncate text-[11px] font-medium text-ink-3">{{ room.state.summary }}</span>
                    </a>
                    <div class="relative mr-5 h-[60px]" :class="room.actions?.book ? 'cursor-pointer' : ''"
                         @mousemove="onMove(room, $event)" @mouseleave="hover = null" @click="onPick(room, $event)">
                        <!-- Hour lines, and half-hour lines a shade lighter -->
                        <span v-for="h in hours" :key="h.label" data-grid="1" class="pointer-events-none absolute top-0 bottom-0 w-px bg-line-soft" :style="{ left: `${h.left}%` }"></span>
                        <span v-for="(h, i) in hours.slice(0, -1)" :key="`half-${h.label}`" class="pointer-events-none absolute top-3 bottom-3 w-px border-l border-dashed border-line-soft"
                              :style="{ left: `${(h.left + hours[i + 1].left) / 2}%` }"></span>

                        <span v-if="hover?.roomId === room.id" class="pointer-events-none absolute top-2 bottom-2 flex items-center gap-1 overflow-hidden rounded-md border border-dashed border-navy/40 bg-navy-50/60 px-2 text-[11px] font-semibold whitespace-nowrap text-navy"
                              :style="{ left: `calc(${hover.left}% + 1px)`, width: `calc(${hover.width}% - 2px)` }"><Icon name="plus" :size="11" :stroke="2.5" />Book</span>

                        <button v-for="(b, i) in room.blocks" :key="i" type="button" :title="`${b.title}, ${b.time}`" @click.stop="open = b" @mousemove.stop="hover = null"
                                class="absolute top-2 bottom-2 flex min-w-0 cursor-pointer flex-col justify-center overflow-hidden rounded-md border-l-[3px] px-2 text-left leading-tight transition-shadow hover:z-20 hover:shadow-md focus-visible:z-20 focus-visible:outline-2 focus-visible:outline-navy"
                                :class="block[b.tone]" :style="{ left: `calc(${b.left}% + 1px)`, width: `calc(${b.width}% - 2px)` }">
                            <span class="flex items-center gap-1 truncate text-[11px] font-semibold">
                                <svg v-if="b.joinUrl" class="flex-none" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-label="Online meeting"><path d="m16 13 5.2 3.5a.5.5 0 0 0 .8-.4V7.9a.5.5 0 0 0-.8-.4L16 11" /><rect x="2" y="6" width="14" height="12" rx="2" /></svg>
                                <span class="truncate">{{ b.title }}</span>
                            </span>
                            <span class="truncate text-[10px] tabular-nums opacity-75">{{ b.time }}</span>
                        </button>

                        <span v-if="nowLeft !== null" class="pointer-events-none absolute top-0 bottom-0 z-10 w-0.5 bg-reserved" :style="{ left: `${nowLeft}%` }"></span>
                    </div>
                </div>

            </div>
          </div>
            <div class="flex flex-wrap gap-4 border-t border-line-soft bg-surface-raised px-5 py-2 text-[11px] text-muted">
                <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-[3px] border-l-2 border-navy bg-navy-50"></span>Coming up</span>
                <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-[3px] border-l-2 border-reserved bg-reserved-bg"></span>In use now</span>
                <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-[3px] border-l-2 border-checkin bg-checkin-bg"></span>Waiting for check-in</span>
                <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-[3px] border-l-2 border-offline bg-surface-raised"></span>Over</span>
                <span class="ml-auto">{{ bookable.length ? 'Click a meeting for details, or an empty spot to book' : 'Click a meeting for its details' }}</span>
            </div>
         </div>
        </Card>

        <div v-else class="grid gap-3.5 sm:grid-cols-2 xl:grid-cols-3">
            <a v-for="room in rooms" :key="room.id" :href="room.url" class="sp-card sp-card-pad-md sp-card-interactive sp-card-rule flex flex-col gap-2.5 text-ink" :class="rule[room.state.key]">
                <span class="flex items-center gap-2">
                    <span class="flex-1 truncate font-semibold">{{ room.name }}</span>
                    <span class="sp-chip sp-chip-neutral">{{ room.state.label }}</span>
                </span>
                <span class="min-h-5 truncate text-[13px] text-ink-3">{{ room.state.meeting || ' ' }}</span>
                <span class="text-xs text-muted">{{ room.state.summary }}</span>
            </a>
        </div>
        <MeetingDetails :meeting="open" :now="now" @close="open = null" @done="done" />
        <BookMeeting :preset="booking" :rooms="bookable" :now="now" @close="booking = null" @done="done" />
    </section>
</template>
