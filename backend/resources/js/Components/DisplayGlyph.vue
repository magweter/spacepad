<script setup>
// A small drawing of the tablet on the wall: the navy bezel with its camera, and the screen in the
// room's status colour with the title, a detail line and a button, as the display shows them.
import { computed } from 'vue';

const props = defineProps({ tone: { type: String, default: 'offline' } }); // available | reserved | checkin | offline

const uid = `glyph-${Math.random().toString(36).slice(2, 9)}`;
const colour = computed(() => `var(--status-${props.tone})`);
const dim = computed(() => props.tone === 'offline');
</script>

<template>
    <svg width="40" height="28" viewBox="0 0 40 28" aria-hidden="true" class="flex-none drop-shadow-[0_1px_1.5px_rgba(15,23,42,0.25)]">
        <defs>
            <linearGradient :id="`${uid}-bezel`" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0" stop-color="var(--brand-navy-700)" />
                <stop offset="1" stop-color="var(--brand-navy)" />
            </linearGradient>
            <linearGradient :id="`${uid}-screen`" x1="0" y1="0" x2="1" y2="1">
                <stop offset="0" :stop-color="colour" />
                <stop offset="1" :stop-color="`color-mix(in srgb, ${colour} 70%, var(--brand-navy))`" />
            </linearGradient>
        </defs>
        <!-- Bezel, with a hairline highlight along the top edge -->
        <rect x="0.5" y="0.5" width="39" height="27" rx="5" :fill="`url(#${uid}-bezel)`" />
        <rect x="1" y="1" width="38" height="26" rx="4.5" fill="none" stroke="white" stroke-opacity="0.12" />
        <circle cx="20" cy="2.6" r="0.75" fill="white" fill-opacity="0.35" />
        <!-- Screen -->
        <rect x="3.5" y="4.5" width="33" height="20" rx="2.5" :fill="`url(#${uid}-screen)`" />
        <rect x="3.5" y="4.5" width="33" height="10" rx="2.5" fill="white" fill-opacity="0.08" />
        <!-- What the display shows: title, detail line and a button -->
        <g :fill-opacity="dim ? 0.55 : 0.95" fill="white">
            <rect x="7" y="9" width="15" height="2.6" rx="1.3" />
            <rect x="7" y="13.6" width="10" height="1.6" rx="0.8" fill-opacity="0.7" />
            <rect x="7" y="18" width="7" height="3" rx="1.5" fill-opacity="0.45" />
        </g>
        <circle cx="31.5" cy="8.5" r="1.1" fill="white" :fill-opacity="dim ? 0.4 : 0.8" />
    </svg>
</template>
