<script setup>
// Alerts the admin marked as a popup, opened right away as the message itself: its image, its type,
// title and text. Several in a row go one by one ("Next"); "Got it", Next or closing marks the
// message as done for this user, after which it no longer shows anywhere in their portal.
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { dismissAlert, doneAlerts } from './alertDismiss';
import Chip from '../DesignSystem/Chip.vue';
import Icon from './Icon.vue';
import { toneOf } from './alertTones.js';

const page = usePage();
// A list on every page; a page with a prop of the same name must not break the shell.
const queue = computed(() => (Array.isArray(page.props.alerts) ? page.props.alerts : []).filter((a) => a.popup && !doneAlerts.value.includes(a.id)));
const alert = computed(() => queue.value[0] ?? null);
const tone = computed(() => toneOf(alert.value?.tone));
// Counted from the popups this page started with, so "2 of 2" stays right while going through them.
const seen = ref([]);
const position = computed(() => seen.value.length + 1);
const total = computed(() => seen.value.length + queue.value.length);

function done() {
    const a = alert.value;
    if (!a) return;
    seen.value.push(a.id);
    dismissAlert(a);
}

const onKey = (e) => { if (e.key === 'Escape' && alert.value) done(); };
onMounted(() => window.addEventListener('keydown', onKey));
onBeforeUnmount(() => window.removeEventListener('keydown', onKey));
</script>

<template>
    <Teleport to="body">
        <div v-if="alert" class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto px-4 pt-[10vh] pb-10">
            <div class="fixed inset-0 bg-navy/45" aria-hidden="true"></div>
            <div role="dialog" aria-modal="true" :aria-label="alert.title" class="relative flex w-full max-w-[560px] flex-col overflow-hidden rounded-[14px] bg-surface text-sm text-ink shadow-2xl">
                <button type="button" aria-label="Close" class="absolute top-3 right-3 z-10 inline-flex h-8 w-8 items-center justify-center rounded-md bg-surface/90 text-muted hover:text-navy" @click="done">
                    <Icon name="close" :size="16" />
                </button>
                <img v-if="alert.image" :key="alert.id" :src="alert.image" alt="" class="max-h-[300px] w-full border-b border-line-soft object-cover">
                <div class="flex flex-col gap-3 px-7 pt-6 pb-2" :class="alert.image ? '' : 'pr-12'">
                    <Chip :tone="tone.chip" class="self-start">{{ tone.label }}</Chip>
                    <h2 class="font-display text-[22px] leading-tight font-bold tracking-tight text-navy">{{ alert.title }}</h2>
                    <p v-if="alert.body" class="text-[14px] leading-relaxed whitespace-pre-line text-ink-2">{{ alert.body }}</p>
                </div>
                <div class="flex items-center gap-3 px-7 pt-4 pb-6">
                    <span v-if="total > 1" class="text-xs text-muted">{{ position }} of {{ total }}</span>
                    <span class="flex-1"></span>
                    <button type="button" class="sp-btn sp-btn-primary sp-btn-md" @click="done">
                        <template v-if="queue.length > 1">Next<Icon name="arrowRight" :size="14" /></template>
                        <template v-else>Got it</template>
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
