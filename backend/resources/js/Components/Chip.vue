<script setup>
defineProps({ selected: { type: Boolean, default: false } });
defineEmits(['select']);
</script>

<template>
    <button type="button" :aria-pressed="selected" @click="$emit('select')"
            class="rounded-lg border px-3 py-1.5 text-[13px] font-medium transition-colors"
            :class="selected ? 'border-navy bg-navy text-white' : 'border-line bg-white text-ink-2 hover:border-slate-300'">
        <slot />
    </button>
</template>
