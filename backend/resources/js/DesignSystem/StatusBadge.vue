<script setup>
// Spacepad Design System: StatusBadge. Colour plus word, never colour alone.
import StatusDot from './StatusDot.vue';

defineProps({ status: { type: String, default: 'available' } }); // available | reserved | checkin | offline
</script>

<template>
    <span :class="['sp-badge', `sp-badge-${status}`]"><StatusDot :status="status" /><slot /></span>
</template>
