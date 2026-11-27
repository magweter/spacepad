<script setup>
// Spacepad Design System: StatusDot. Always paired with a word.
defineProps({
    status: { type: String, default: 'available' }, // available | reserved | checkin | offline
    size: { type: String, default: 'sm' },
    glow: { type: Boolean, default: false },
});
</script>

<template>
    <span :class="['sp-dot', `sp-dot-${size}`, `sp-dot-${status}`, { 'sp-dot-glow': glow }]" aria-hidden="true"></span>
</template>
