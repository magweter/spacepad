<script setup>
import { nextTick, onMounted, ref, watch } from 'vue';
import { useRememberedRef } from '../../../Composables/useRememberedRef';
import MeetingDetails from './MeetingDetails.vue';
import Card from '../../../DesignSystem/Card.vue';
import EmptyState from '../../../DesignSystem/EmptyState.vue';
import Button from '../../../DesignSystem/Button.vue';

const props = defineProps({ rooms: Array, hours: Array, nowLeft: Number, nowLabel: String, urls: Object });

const view = useRememberedRef('overview.rooms.view', 'timeline', ['timeline', 'cards']);
const rail = { free: 'border-l-available', busy: 'border-l-reserved', checkin: 'border-l-checkin', offline: 'border-l-offline' };
const dot = { free: 'bg-available', busy: 'bg-reserved', checkin: 'bg-checkin', offline: 'bg-offline' };
const text = { free: 'text-available-text', busy: 'text-reserved-text', checkin: 'text-checkin-text', offline: 'text-muted' };
const badge = { free: 'bg-available-bg text-available-text', busy: 'bg-reserved-bg text-reserved-text', checkin: 'bg-checkin-bg text-checkin-text', offline: 'bg-offline-bg text-muted' };
// Calendar blocks: a light fill with a coloured edge, so the title stays readable in every state.
const block = {
    past: 'border-offline bg-surface-raised text-muted',
    busy: 'border-reserved bg-reserved-bg text-reserved-text',
    checkin: 'border-checkin bg-checkin-bg text-checkin-text',
    booked: 'border-navy bg-navy-50 text-navy',
};
const open = ref(null);

// Open on the current time: now sits about a third in, with the hour before it in view.
const scroller = ref(null);
async function scrollToNow() {
    await nextTick();
    const el = scroller.value;
    if (!el) return;
    const grid = el.scrollWidth - 190;
    el.scrollLeft = Math.max(0, (props.nowLeft / 100) * grid - (el.clientWidth - 190) / 3);
}
onMounted(scrollToNow);
watch(view, (v) => { if (v === 'timeline') scrollToNow(); });
</script>

<template>
    <section aria-label="Live room status" class="flex flex-col gap-3.5">
        <div class="flex flex-wrap items-center gap-3">
            <h2 class="font-display text-base font-bold tracking-tight">Rooms right now</h2>
            <div class="flex-1"></div>
            <div v-if="rooms.length" class="inline-flex overflow-hidden rounded-lg border border-line bg-surface">
                <button v-for="v in ['timeline', 'cards']" :key="v" type="button" :aria-pressed="view === v" @click="view = v"
                        class="px-3 py-1.5 text-xs font-semibold capitalize" :class="view === v ? 'bg-navy text-white' : 'text-ink-3 hover:bg-surface-raised'">{{ v }}</button>
            </div>
            <a :href="urls.displays" class="text-[13px] font-semibold text-navy hover:underline">All displays →</a>
        </div>

        <EmptyState v-if="!rooms.length" title="No displays yet">Set up a display and its room shows up here, live.
            <template #actions><Button :href="urls.newDisplay" external>Set up a display</Button></template>
        </EmptyState>

        <!-- Timeline as a day calendar: an hour grid per room, each meeting a block that opens its details. -->
        <Card v-else-if="view === 'timeline'" padding="none" class="overflow-hidden">
         <div class="min-w-0">
          <div ref="scroller" class="overflow-x-auto">
            <!-- About 110 px per hour, so a half-hour meeting still shows its title; scrolls sideways. -->
            <div :style="{ minWidth: `${190 + (hours.length - 1) * 110}px` }">
                <div class="grid grid-cols-[170px_1fr] border-b border-line sm:grid-cols-[190px_1fr]">
                    <span class="sticky left-0 z-30 bg-surface px-5 py-2.5 text-[11px] font-semibold tracking-[0.04em] text-muted uppercase">Room</span>
                    <div class="relative mr-5 h-[34px] text-[11px] text-muted tabular-nums">
                        <span v-for="(h, i) in hours" :key="h.label" class="absolute top-2.5 -translate-x-1/2"
                              :class="i === 0 ? 'translate-x-0' : i === hours.length - 1 ? '-translate-x-full' : ''" :style="{ left: `${h.left}%` }">{{ h.label }}</span>
                        <span class="absolute top-1.5 z-10 -translate-x-1/2 rounded-full bg-reserved px-1.5 py-0.5 text-[10px] font-semibold text-white tabular-nums" :style="{ left: `${nowLeft}%` }">{{ nowLabel }}</span>
                    </div>
                </div>

                <div v-for="room in rooms" :key="room.id" class="grid grid-cols-[170px_1fr] border-b border-l-[3px] border-b-line-soft sm:grid-cols-[190px_1fr]" :class="rail[room.state.key]">
                    <a :href="room.url" class="sticky left-0 z-30 flex min-w-0 flex-col justify-center border-r border-line-soft bg-surface py-3 pr-3 pl-[17px] text-ink hover:bg-surface-raised">
                        <span class="truncate text-[13px] font-semibold">{{ room.name }}</span>
                        <span class="truncate text-[11px] font-medium" :class="text[room.state.key]">{{ room.state.summary }}</span>
                    </a>
                    <div class="relative mr-5 h-[60px]">
                        <!-- Hour lines, and half-hour lines a shade lighter -->
                        <span v-for="h in hours" :key="h.label" class="absolute top-0 bottom-0 w-px bg-line-soft" :style="{ left: `${h.left}%` }"></span>
                        <span v-for="(h, i) in hours.slice(0, -1)" :key="`half-${h.label}`" class="absolute top-3 bottom-3 w-px border-l border-dashed border-line-soft"
                              :style="{ left: `${(h.left + hours[i + 1].left) / 2}%` }"></span>

                        <button v-for="(b, i) in room.blocks" :key="i" type="button" :title="`${b.title}, ${b.time}`" @click="open = b"
                                class="absolute top-2 bottom-2 flex min-w-0 cursor-pointer flex-col justify-center overflow-hidden rounded-md border-l-[3px] px-2 text-left leading-tight transition-shadow hover:z-20 hover:shadow-md focus-visible:z-20 focus-visible:outline-2 focus-visible:outline-navy"
                                :class="block[b.tone]" :style="{ left: `calc(${b.left}% + 1px)`, width: `calc(${b.width}% - 2px)` }">
                            <span class="flex items-center gap-1 truncate text-[11px] font-semibold">
                                <svg v-if="b.joinUrl" class="flex-none" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-label="Online meeting"><path d="m16 13 5.2 3.5a.5.5 0 0 0 .8-.4V7.9a.5.5 0 0 0-.8-.4L16 11" /><rect x="2" y="6" width="14" height="12" rx="2" /></svg>
                                <span class="truncate">{{ b.title }}</span>
                            </span>
                            <span class="truncate text-[10px] tabular-nums opacity-75">{{ b.time }}</span>
                        </button>

                        <span class="pointer-events-none absolute top-0 bottom-0 z-10 w-0.5 bg-reserved" :style="{ left: `${nowLeft}%` }"></span>
                    </div>
                </div>

            </div>
          </div>
            <div class="flex flex-wrap gap-4 border-t border-line-soft bg-surface-raised px-5 py-2 text-[11px] text-muted">
                <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-[3px] border-l-2 border-navy bg-navy-50"></span>Coming up</span>
                <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-[3px] border-l-2 border-reserved bg-reserved-bg"></span>In use now</span>
                <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-[3px] border-l-2 border-checkin bg-checkin-bg"></span>Waiting for check-in</span>
                <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-[3px] border-l-2 border-offline bg-surface-raised"></span>Earlier today</span>
                <span class="ml-auto">Click a meeting for its details</span>
            </div>
         </div>
        </Card>

        <div v-else class="grid gap-3.5 sm:grid-cols-2 xl:grid-cols-3">
            <a v-for="room in rooms" :key="room.id" :href="room.url" class="sp-card sp-card-pad-md sp-card-interactive flex flex-col gap-2.5 text-ink">
                <span class="flex items-center gap-2">
                    <span class="h-2.5 w-2.5 flex-none rounded-full" :class="dot[room.state.key]"></span>
                    <span class="flex-1 truncate font-semibold">{{ room.name }}</span>
                    <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold whitespace-nowrap" :class="badge[room.state.key]">{{ room.state.label }}</span>
                </span>
                <span class="min-h-5 truncate text-[13px] text-ink-3">{{ room.state.meeting || ' ' }}</span>
                <span class="text-xs text-muted">{{ room.state.summary }}</span>
            </a>
        </div>
        <MeetingDetails :meeting="open" @close="open = null" />
    </section>
</template>
