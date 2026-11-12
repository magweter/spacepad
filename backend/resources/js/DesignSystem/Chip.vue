<script setup>
// Spacepad Design System: Chip. Outlined, sentence case, never a filled pill.
defineProps({ tone: { type: String, default: 'neutral' } }); // info | amber | neutral | navy | available | reserved
</script>

<template>
    <span :class="['sp-chip', `sp-chip-${tone}`]"><slot /></span>
</template>
