<script setup>
// Previous, the numbered pages (with gaps) and next, plus which rows are shown.
import { Link } from '@inertiajs/vue3';

defineProps({ pagination: { type: Object, required: true } });

const item = 'inline-flex min-w-8 items-center justify-center rounded-lg border px-2.5 py-1.5 text-[13px] font-medium';
</script>

<template>
    <nav v-if="pagination.lastPage > 1" aria-label="Pagination" class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-[13px] text-muted">
            Showing <span class="font-semibold text-ink-2">{{ pagination.from }}</span> to <span class="font-semibold text-ink-2">{{ pagination.to }}</span>
            of <span class="font-semibold text-ink-2">{{ pagination.total }}</span> results
        </p>
        <div class="flex flex-wrap items-center gap-1">
            <Link v-if="pagination.prev" :href="pagination.prev" preserve-scroll :class="[item, 'border-line bg-surface text-ink hover:bg-surface-raised']">Previous</Link>
            <span v-else :class="[item, 'border-line-soft text-muted']" aria-disabled="true">Previous</span>
            <template v-for="(l, i) in pagination.links" :key="i">
                <span v-if="!l.url" :class="[item, 'border-transparent text-muted']">{{ l.label }}</span>
                <span v-else-if="l.active" aria-current="page" :class="[item, 'border-navy bg-navy text-white']">{{ l.label }}</span>
                <Link v-else :href="l.url" preserve-scroll :class="[item, 'border-line bg-surface text-ink hover:bg-surface-raised']" :aria-label="`Go to page ${l.label}`">{{ l.label }}</Link>
            </template>
            <Link v-if="pagination.next" :href="pagination.next" preserve-scroll :class="[item, 'border-line bg-surface text-ink hover:bg-surface-raised']">Next</Link>
            <span v-else :class="[item, 'border-line-soft text-muted']" aria-disabled="true">Next</span>
        </div>
    </nav>
</template>
