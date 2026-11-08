<script setup>
defineProps({
    previous: { type: String, default: null },
    continueLabel: { type: String, default: 'Continue' },
    processing: { type: Boolean, default: false },
    showSkip: { type: Boolean, default: true },
    skipLabel: { type: String, default: 'Skip this step' },
    continueDisabled: { type: Boolean, default: false },
});
defineEmits(['back', 'skip', 'skip-setup', 'continue']);
</script>

<template>
    <div class="flex flex-wrap items-center gap-3 border-t border-line pt-5">
        <button v-if="previous" type="button" class="text-[13px] font-semibold text-muted hover:text-navy" @click="$emit('back')">← Back</button>
        <button type="button" class="text-[13px] font-semibold text-muted hover:text-navy" @click="$emit('skip-setup')">Skip setup</button>
        <div class="flex-1"></div>
        <button v-if="showSkip" type="button" class="rounded-[10px] px-4 py-2.5 text-[13px] font-semibold text-ink-3 hover:bg-white" @click="$emit('skip')">{{ skipLabel }}</button>
        <button type="button" :disabled="processing || continueDisabled"
                class="rounded-[10px] bg-navy px-5 py-2.5 text-sm font-semibold text-white hover:bg-navy-800 disabled:opacity-50"
                @click="$emit('continue')">{{ continueLabel }}</button>
    </div>
</template>
