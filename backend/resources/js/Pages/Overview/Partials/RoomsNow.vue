<script setup>
import { ref } from 'vue';
import Card from '../../../DesignSystem/Card.vue';
import EmptyState from '../../../DesignSystem/EmptyState.vue';
import Button from '../../../DesignSystem/Button.vue';

defineProps({ rooms: Array, hours: Array, nowLeft: Number, nowLabel: String, updated: String, urls: Object });

const view = ref('timeline');
const rail = { free: 'border-l-available', busy: 'border-l-reserved', checkin: 'border-l-checkin', offline: 'border-l-offline' };
const dot = { free: 'bg-available', busy: 'bg-reserved', checkin: 'bg-checkin', offline: 'bg-offline' };
const text = { free: 'text-available-text', busy: 'text-reserved-text', checkin: 'text-checkin-text', offline: 'text-muted' };
const badge = { free: 'bg-available-bg text-available-text', busy: 'bg-reserved-bg text-reserved-text', checkin: 'bg-checkin-bg text-checkin-text', offline: 'bg-offline-bg text-muted' };
const block = { past: 'bg-line text-ink-3', busy: 'bg-reserved text-white', checkin: 'bg-checkin text-navy', booked: 'bg-navy text-white' };
</script>

<template>
    <section aria-label="Live room status" class="flex flex-col gap-3.5">
        <div class="flex flex-wrap items-center gap-3">
            <h2 class="font-display text-base font-bold tracking-tight">Rooms right now</h2>
            <span v-if="rooms.length" class="text-xs text-muted">Updated {{ updated }}</span>
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

        <Card v-else-if="view === 'timeline'" padding="none" class="overflow-hidden">
          <div>
            <div class="grid grid-cols-[150px_1fr] gap-x-4 border-b border-line px-5 pt-2 pb-1.5 text-[11px] text-muted tabular-nums sm:grid-cols-[180px_1fr]">
                <span class="font-semibold tracking-[0.04em] uppercase">Room</span>
                <div class="flex justify-between"><span v-for="h in hours" :key="h">{{ h }}</span></div>
            </div>
            <a v-for="room in rooms" :key="room.id" :href="room.url"
               class="grid grid-cols-[150px_1fr] items-center gap-x-4 border-b border-l-[3px] border-b-line-soft py-2.5 pr-5 pl-[17px] text-ink transition-colors hover:bg-surface-raised sm:grid-cols-[180px_1fr]"
               :class="rail[room.state.key]">
                <span class="flex min-w-0 flex-col">
                    <span class="truncate text-[13px] font-semibold">{{ room.name }}</span>
                    <span class="truncate text-[11px] font-medium" :class="text[room.state.key]">{{ room.state.summary }}</span>
                </span>
                <span class="relative block h-[26px] overflow-hidden rounded-md bg-line-soft">
                    <span v-for="(b, i) in room.blocks" :key="i" :title="b.title"
                          class="absolute top-1 bottom-1 inline-flex items-center overflow-hidden rounded px-2 text-[11px] font-semibold whitespace-nowrap"
                          :class="block[b.tone]" :style="{ left: `${b.left}%`, width: `${b.width}%` }">{{ b.title }}</span>
                    <span class="absolute top-0 bottom-0 w-0.5 bg-ink" :style="{ left: `${nowLeft}%` }"></span>
                </span>
            </a>
            <div class="flex flex-wrap gap-4 bg-surface-raised px-5 py-2 text-[11px] text-muted">
                <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-[3px] bg-navy"></span>Booked</span>
                <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-[3px] bg-reserved"></span>In use now</span>
                <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-[3px] bg-checkin"></span>Waiting for check-in</span>
                <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-[3px] bg-line"></span>Earlier today</span>
                <span class="ml-auto">The dark line is now, {{ nowLabel }}</span>
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
    </section>
</template>
