<script setup>
// A segmented control from the Boards design: one bordered row of options, the pick filled navy.
defineProps({
    modelValue: { type: String, default: null },
    options: { type: Array, required: true }, // [{ value, label }]
    label: { type: String, required: true },
});
defineEmits(['update:modelValue']);
</script>

<template>
    <div role="radiogroup" :aria-label="label" class="inline-flex overflow-hidden rounded-lg border border-line bg-surface">
        <button v-for="o in options" :key="o.value" type="button" role="radio" :aria-checked="modelValue === o.value ? 'true' : 'false'"
                class="px-3.5 py-2 text-xs transition-colors"
                :class="modelValue === o.value ? 'bg-navy font-semibold text-white' : 'font-medium text-ink-2 hover:bg-surface-raised'"
                @click="$emit('update:modelValue', o.value)">{{ o.label }}</button>
    </div>
</template>
