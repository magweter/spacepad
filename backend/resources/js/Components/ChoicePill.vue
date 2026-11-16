<script setup>
defineProps({ selected: { type: Boolean, default: false } });
defineEmits(['select']);
</script>

<template>
    <button type="button" :aria-pressed="selected" @click="$emit('select')"
            class="rounded-full border-2 px-4 py-[9px] text-[13px] font-semibold transition-colors"
            :class="selected ? 'border-navy bg-navy text-white' : 'border-line bg-surface text-ink-2 hover:border-navy'">
        <slot />
    </button>
</template>
