<script setup>
// A label on the left, its control on the right; stacks on a narrow screen.
defineProps({
    label: { type: String, required: true },
    for: { type: String, default: null },
    help: { type: String, default: null },
    error: { type: String, default: null },
});
</script>

<template>
    <div class="flex flex-wrap items-start gap-x-3.5 gap-y-1.5">
        <label :for="$props.for" class="flex-[0_0_150px] pt-2 text-[13px] font-medium">{{ label }}</label>
        <div class="flex min-w-0 flex-[1_1_200px] flex-col gap-1">
            <slot />
            <span v-if="help" class="text-xs text-muted">{{ help }}</span>
            <span v-if="error" class="text-xs font-medium text-reserved-text">{{ error }}</span>
        </div>
    </div>
</template>
