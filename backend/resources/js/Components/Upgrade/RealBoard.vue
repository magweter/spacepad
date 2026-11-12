<script setup>
// A board as the lobby TV shows it, drawn after the real board page: the organisation's logo and
// title, the clock, and the rooms per floor with their status and what is going on.
import { computed } from 'vue';

const props = defineProps({ size: { type: Number, default: 520 } });

const k = computed(() => props.size / 520);
const px = (v) => `${(v * k.value).toFixed(2)}px`;

const floors = [
    {
        name: 'Floor 1',
        rooms: [
            { name: 'Boardroom', status: 'reserved', word: 'Reserved', line: 'Q4 planning, until 10:30' },
            { name: 'Focus 1', status: 'available', word: 'Available', line: 'Free until 11:00' },
            { name: 'Atlas', status: 'available', word: 'Available', line: 'Available until end of day' },
        ],
    },
    {
        name: 'Floor 2',
        rooms: [
            { name: 'Studio', status: 'reserved', word: 'Reserved', line: 'Client call, until 10:15' },
            { name: 'Lounge', status: 'available', word: 'Available', line: 'Free until 13:00' },
            { name: 'Focus 2', status: 'reserved', word: 'Reserved', line: 'Interview, until 11:00' },
        ],
    },
];
</script>

<template>
    <div class="bg-console-ground" :style="{ width: px(520), padding: px(8), borderRadius: px(10), boxShadow: '0 20px 44px rgba(0, 0, 0, 0.35), inset 0 0 0 1px var(--brand-navy-700)' }">
        <div class="flex aspect-video flex-col overflow-hidden text-white" :style="{ background: '#0F1729', padding: `${px(14)} ${px(16)}`, gap: px(10), borderRadius: px(3) }">
            <div class="flex items-center justify-between">
                <span class="inline-flex items-center font-semibold" :style="{ gap: px(7), fontSize: px(12) }">
                    <span class="inline-flex items-center justify-center rounded bg-info text-white" :style="{ width: px(20), height: px(20), borderRadius: px(5) }">
                        <svg :width="px(12)" :height="px(12)" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m3 20 6-11 4 7 3-5 5 9z" /></svg>
                    </span>
                    Meeting rooms
                </span>
                <span class="flex flex-col items-end leading-tight">
                    <b class="font-mono tabular-nums" :style="{ fontSize: px(13) }">09:48</b>
                    <span class="text-white/50" :style="{ fontSize: px(6.5) }">Thursday, 12 November</span>
                </span>
            </div>
            <div class="grid flex-1 grid-cols-2" :style="{ gap: px(12) }">
                <div v-for="f in floors" :key="f.name" class="flex flex-col" :style="{ gap: px(6) }">
                    <span class="font-semibold tracking-wide text-white/50 uppercase" :style="{ fontSize: px(6.5) }">{{ f.name }}</span>
                    <div v-for="r in f.rooms" :key="r.name" class="flex items-center bg-white/[0.04]"
                         :style="{ gap: px(8), padding: `${px(7)} ${px(8)}`, borderRadius: px(4), borderLeft: `${px(2.5)} solid var(--status-${r.status})` }">
                        <span class="font-semibold tracking-wide uppercase" :style="{ fontSize: px(5.5), padding: `${px(2)} ${px(4)}`, borderRadius: px(3), color: `var(--status-${r.status})`, border: `1px solid color-mix(in srgb, var(--status-${r.status}) 45%, transparent)`, minWidth: px(44), textAlign: 'center' }">{{ r.word }}</span>
                        <span class="flex min-w-0 flex-col leading-tight">
                            <b class="truncate" :style="{ fontSize: px(9) }">{{ r.name }}</b>
                            <span class="truncate text-white/55" :style="{ fontSize: px(6.5) }">{{ r.line }}</span>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
