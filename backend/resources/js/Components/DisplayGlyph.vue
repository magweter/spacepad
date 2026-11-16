<script setup>
// A small, flat drawing of a room display as the app lays it out: the dark screen inside a frame in
// the status colour, the clock and room name on top, the title and its detail line, and the bar
// with the next meeting along the bottom.
import { computed } from 'vue';

const props = defineProps({
    tone: { type: String, default: 'offline' }, // available | reserved | checkin | offline
    width: { type: Number, default: 40 },
});

const colour = computed(() => `var(--status-${props.tone})`);
const ink = computed(() => (props.tone === 'offline' ? 0.45 : 0.9));
</script>

<template>
    <svg :width="width" :height="width * 0.7" viewBox="0 0 40 28" aria-hidden="true" class="flex-none">
        <!-- Device -->
        <rect x="0.25" y="0.25" width="39.5" height="27.5" rx="4" fill="var(--brand-navy)" stroke="var(--brand-navy-700)" stroke-width="0.5" />
        <!-- Status frame and the dark screen inside it -->
        <rect x="2" y="2" width="36" height="24" rx="2.2" :fill="colour" />
        <rect x="4.6" y="4.6" width="30.8" height="18.8" rx="1" fill="var(--console-ground)" />
        <g fill="white">
            <!-- Clock and room name -->
            <rect x="6.6" y="6.6" width="5" height="1.4" rx="0.7" :fill-opacity="ink" />
            <rect x="27.4" y="6.6" width="6" height="1.4" rx="0.7" :fill-opacity="ink * 0.7" />
            <!-- Title and detail -->
            <rect x="6.6" y="10.6" width="14" height="2.8" rx="1" :fill-opacity="ink" />
            <rect x="6.6" y="14.7" width="9" height="1.4" rx="0.7" :fill-opacity="ink * 0.55" />
            <!-- Next meeting bar -->
            <rect x="6.6" y="18.6" width="26.8" height="2.8" rx="1" :fill-opacity="ink * 0.22" />
        </g>
    </svg>
</template>
