<script setup>
// Draws the toasts from toasts.js: the design system's navy toast, with a status dot for the tone.
import Icon from '../Icon.vue';
import StatusDot from '../../DesignSystem/StatusDot.vue';
import { dismiss, toasts } from './toasts';

const dot = { danger: 'reserved', success: 'available', warning: 'checkin' };
</script>

<template>
    <div class="pointer-events-none fixed right-4 bottom-4 left-4 z-[60] flex flex-col items-end gap-2 sm:left-auto" aria-live="polite">
        <TransitionGroup enter-from-class="translate-y-2 opacity-0" leave-to-class="opacity-0"
                         enter-active-class="transition duration-200" leave-active-class="transition duration-200">
            <div v-for="t in toasts" :key="t.id" class="sp-notice-toast pointer-events-auto w-full sm:w-[400px]"
                 :role="t.tone === 'danger' ? 'alert' : 'status'">
                <StatusDot v-if="dot[t.tone]" :status="dot[t.tone]" />
                <span class="sp-notice-text flex min-w-0 flex-1 flex-col text-[13px]">
                    <span class="font-semibold">{{ t.title }}</span>
                    <span v-if="t.body" class="sp-notice-body text-xs">{{ t.body }}</span>
                </span>
                <button type="button" class="sp-notice-close" aria-label="Close" @click="dismiss(t.id)"><Icon name="close" :size="14" /></button>
            </div>
        </TransitionGroup>
    </div>
</template>
