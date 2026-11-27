<script setup>
// A compact list card on the overview (design 01d), as used for Boards and Profiles: a heading,
// a link to the full page, a "New" button and a few rows.
import { Link } from '@inertiajs/vue3';
import Icon from '../../../Components/Icon.vue';

defineProps({
    title: { type: String, required: true },
    allUrl: { type: String, required: true },
    newUrl: { type: String, default: null },
    newLabel: { type: String, default: null },
    total: { type: Number, default: 0 },
    shown: { type: Number, default: 0 },
    empty: { type: String, default: null },
});
</script>

<template>
    <section :aria-label="title" class="sp-card min-w-0 gap-0 overflow-hidden">
        <div class="flex items-center gap-3 px-5 pt-4 pb-3">
            <h2 class="font-display text-lg font-bold tracking-tight text-navy">{{ title }}</h2>
            <span class="flex-1"></span>
            <Link :href="allUrl" class="sp-btn sp-btn-secondary sp-btn-sm">Show all<Icon name="arrowRight" :size="14" /></Link>
            <Link v-if="newUrl" :href="newUrl" class="sp-btn sp-btn-secondary sp-btn-sm"><Icon name="plus" :size="14" :stroke="2.5" />{{ newLabel }}</Link>
        </div>
        <div class="flex flex-col divide-y divide-line-soft border-t border-line-soft">
            <slot />
            <p v-if="!shown && empty" class="px-5 py-4 text-[13px] text-muted">{{ empty }}</p>
        </div>
        <Link v-if="total > shown" :href="allUrl" class="border-t border-line-soft px-5 py-2.5 text-xs text-muted hover:text-navy">{{ total - shown }} more</Link>
    </section>
</template>
