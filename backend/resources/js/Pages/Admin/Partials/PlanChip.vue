<script setup>
// The plan of a workspace as one chip, a distinct colour per plan so a long list scans at a glance.
// Pro is blue, Enterprise navy, Manually billed amber, Unlimited green, Free grey; a subscription
// that needs attention (past due, cancelled, paused) is red with its own word.
import { computed } from 'vue';
import Chip from '../../../DesignSystem/Chip.vue';

const props = defineProps({ plan: { type: Object, required: true } }); // { key, label }

const tones = { pro: 'info', trial: 'info', enterprise: 'navy', manual: 'amber', unlimited: 'available', free: 'neutral' };
const tone = computed(() => tones[props.plan.key] ?? 'reserved');
</script>

<template>
    <Chip :tone="tone">{{ plan.label }}</Chip>
</template>
