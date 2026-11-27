<script setup>
// A choice drawn as a card, as in the onboarding design: the whole card is the control, a 2px
// navy border marks the pick. size "lg" is the big two-up card with a radio in the corner.
defineProps({
    selected: { type: Boolean, default: false },
    type: { type: String, default: 'radio' }, // radio | checkbox
    size: { type: String, default: 'md' }, // md | lg
});
defineEmits(['select']);
</script>

<template>
    <button type="button" :role="type" :aria-checked="selected" @click="$emit('select')"
            class="relative flex w-full border-2 text-left text-ink transition-colors hover:border-navy"
            :class="[
                size === 'lg' ? 'flex-col gap-2.5 rounded-[14px] bg-surface p-[18px]' : 'items-center gap-2.5 rounded-lg px-3.5 py-3',
                selected ? (size === 'lg' ? 'border-navy' : 'border-navy bg-surface-raised') : 'border-line bg-surface',
            ]">
        <span v-if="type === 'checkbox'" class="flex h-[18px] w-[18px] flex-none items-center justify-center rounded-[5px] border-2 text-[11px] font-bold text-white"
              :class="selected ? 'border-navy bg-navy' : 'border-offline-border bg-surface'">{{ selected ? '✓' : '' }}</span>
        <slot />
        <span v-if="size === 'lg'" class="absolute top-3.5 right-3.5 flex h-[22px] w-[22px] items-center justify-center rounded-full border-2"
              :class="selected ? 'border-navy' : 'border-offline-border'">
            <span class="h-2.5 w-2.5 rounded-full" :class="selected ? 'bg-navy' : ''"></span>
        </span>
    </button>
</template>
