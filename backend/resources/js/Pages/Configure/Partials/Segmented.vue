<script setup>
// A small segmented control: one of a few options, as in the design's theme and preview switches.
defineProps({
    modelValue: { type: [String, Number], default: null },
    options: { type: Array, required: true }, // [{ value, label }]
    label: { type: String, required: true },
});
defineEmits(['update:modelValue']);
</script>

<template>
    <div role="radiogroup" :aria-label="label" class="inline-flex self-start overflow-hidden rounded-md border border-line bg-surface">
        <button v-for="o in options" :key="o.value" type="button" role="radio" :aria-checked="modelValue === o.value ? 'true' : 'false'"
                class="border-l border-line px-3 py-1.5 text-xs font-semibold first:border-l-0"
                :class="modelValue === o.value ? 'bg-navy text-white' : 'text-ink-3 hover:bg-surface-raised'"
                @click="$emit('update:modelValue', o.value)">{{ o.label }}</button>
    </div>
</template>
