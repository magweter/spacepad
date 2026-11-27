<script setup>
// A roadmap item's status as a chip: the label, with the tone that goes with it.
import { computed } from 'vue';
import Chip from '../../../DesignSystem/Chip.vue';

const props = defineProps({ status: { type: Object, required: true } }); // { value, label }

const tones = { considering: 'neutral', planned: 'info', building: 'amber', shipped: 'available' };
const tone = computed(() => tones[props.status.value] ?? 'neutral');
</script>

<template>
    <Chip :tone="tone">{{ status.label }}</Chip>
</template>
