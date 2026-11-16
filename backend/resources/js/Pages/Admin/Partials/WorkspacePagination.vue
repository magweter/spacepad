<script setup>
// Page links for a Laravel length-aware paginator, as Inertia serialises it. Hidden on a single page.
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({ paginator: { type: Object, required: true } });

// Laravel labels the first and last link with HTML entities; the numbers in between are plain.
const pages = computed(() => props.paginator.links.slice(1, -1));
const linkClass = 'inline-flex min-w-8 items-center justify-center rounded-lg border px-2.5 py-1.5 text-[13px] font-medium';
</script>

<template>
    <nav v-if="paginator.last_page > 1" aria-label="Pagination" class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-[13px] text-muted">
            Showing <span class="font-semibold text-ink-2">{{ paginator.from }}</span> to <span class="font-semibold text-ink-2">{{ paginator.to }}</span>
            of <span class="font-semibold text-ink-2">{{ paginator.total }}</span> results
        </p>
        <div class="flex flex-wrap items-center gap-1">
            <Link v-if="paginator.prev_page_url" :href="paginator.prev_page_url" :class="[linkClass, 'border-line bg-surface text-ink hover:bg-surface-raised']" preserve-scroll>Previous</Link>
            <span v-else :class="[linkClass, 'border-line-soft text-muted']" aria-disabled="true">Previous</span>
            <template v-for="(p, i) in pages" :key="i">
                <span v-if="!p.url" :class="[linkClass, 'border-transparent text-muted']">{{ p.label }}</span>
                <Link v-else :href="p.url" :aria-current="p.active ? 'page' : null"
                      :class="[linkClass, p.active ? 'border-navy bg-navy text-white' : 'border-line bg-surface text-ink hover:bg-surface-raised']">{{ p.label }}</Link>
            </template>
            <Link v-if="paginator.next_page_url" :href="paginator.next_page_url" :class="[linkClass, 'border-line bg-surface text-ink hover:bg-surface-raised']" preserve-scroll>Next</Link>
            <span v-else :class="[linkClass, 'border-line-soft text-muted']" aria-disabled="true">Next</span>
        </div>
    </nav>
</template>
