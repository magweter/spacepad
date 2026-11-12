<script setup>
defineProps({
    eyebrow: { type: String, required: true },
    title: { type: String, required: true },
});
</script>

<template>
    <div class="flex flex-col gap-1.5">
        <span class="text-xs font-semibold tracking-[0.06em] text-muted uppercase">{{ eyebrow }}</span>
        <slot name="before-title" />
        <h2 class="text-[28px] leading-tight font-bold tracking-tight">{{ title }}</h2>
        <p v-if="$slots.default" class="text-muted"><slot /></p>
    </div>
</template>
