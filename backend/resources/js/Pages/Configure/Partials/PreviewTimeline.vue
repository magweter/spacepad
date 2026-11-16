<script setup>
// The day timeline as the app draws it: a frosted panel with "Today", the hours down the side and
// the bookings on them. Two example meetings.
defineProps({ panel: { type: [String, Array], required: true }, radius: String, fontSize: String, meetings: Array });
const hours = ['09:00', '10:00', '11:00', '12:00', '13:00'];
</script>

<template>
    <div class="flex min-h-0 flex-col gap-2 px-3 py-3" :class="[panel, radius]" :style="{ fontSize }">
        <span class="font-semibold">Today</span>
        <div class="relative min-h-0 flex-1">
            <div v-for="(h, i) in hours" :key="h" class="absolute right-0 left-0 flex items-start gap-1.5" :style="{ top: `${(i / (hours.length - 1)) * 100}%` }">
                <span class="w-[30px] flex-none -translate-y-1/2 text-[0.8em] text-[var(--text-muted)] tabular-nums">{{ h }}</span>
                <span class="h-px flex-1 bg-white/10"></span>
            </div>
            <div v-for="m in meetings" :key="m.title + m.from" class="absolute right-0 left-[36px] overflow-hidden rounded-[4px] border-l-[3px] border-[var(--status-reserved)] bg-white/10 px-1.5 py-1 leading-tight"
                 :style="{ top: `${m.from * 100}%`, height: `${m.length * 100}%` }">
                <span class="block truncate font-semibold">{{ m.title }}</span>
            </div>
        </div>
    </div>
</template>
