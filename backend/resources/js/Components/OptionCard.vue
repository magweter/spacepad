<script setup>
defineProps({
    selected: { type: Boolean, default: false },
    type: { type: String, default: 'radio' },
});
defineEmits(['select']);
</script>

<template>
    <!-- A choice drawn as a card: the whole card is the control. -->
    <button type="button" :role="type" :aria-checked="selected" @click="$emit('select')"
            class="flex w-full items-start gap-3 rounded-xl border px-4 py-3.5 text-left transition-colors"
            :class="selected ? 'border-navy bg-surface-raised' : 'border-line bg-white hover:border-slate-300'">
        <span class="mt-0.5 flex h-4 w-4 flex-none items-center justify-center border-2"
              :class="[type === 'checkbox' ? 'rounded' : 'rounded-full', selected ? 'border-navy bg-navy' : 'border-slate-300']">
            <svg v-if="selected && type === 'checkbox'" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5" /></svg>
            <span v-else-if="selected" class="h-1.5 w-1.5 rounded-full bg-white"></span>
        </span>
        <span class="flex min-w-0 flex-col gap-0.5"><slot /></span>
    </button>
</template>
