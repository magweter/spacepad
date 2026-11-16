<script setup>
// Spacepad Design System: modal dialog. Closes on Escape and on a click outside.
import { onBeforeUnmount, onMounted } from 'vue';

const props = defineProps({ open: Boolean, title: { type: String, required: true }, width: { type: String, default: '520px' } });
const emit = defineEmits(['close']);

const onKey = (e) => { if (e.key === 'Escape' && props.open) emit('close'); };
onMounted(() => window.addEventListener('keydown', onKey));
onBeforeUnmount(() => window.removeEventListener('keydown', onKey));
</script>

<template>
    <Teleport to="body">
        <div v-if="open" class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto px-4 pt-[10vh] pb-10">
            <button type="button" aria-label="Close" class="fixed inset-0 cursor-default bg-navy/45" @click="emit('close')"></button>
            <div role="dialog" aria-modal="true" :aria-label="title" class="relative flex w-full flex-col gap-4 rounded-[14px] bg-surface p-6 text-sm text-ink shadow-2xl" :style="{ maxWidth: width }">
                <div class="flex items-start justify-between gap-4">
                    <h2 class="font-display text-lg font-bold tracking-tight">{{ title }}</h2>
                    <button type="button" aria-label="Close" class="text-muted hover:text-ink" @click="emit('close')">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12" /></svg>
                    </button>
                </div>
                <slot />
                <div v-if="$slots.footer" class="flex flex-wrap items-center justify-end gap-2 border-t border-line-soft pt-4"><slot name="footer" /></div>
            </div>
        </div>
    </Teleport>
</template>
