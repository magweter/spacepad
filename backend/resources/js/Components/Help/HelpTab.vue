<script setup>
// Help tab: search and filter the FAQ, open an answer, or ask a question.
import { computed, ref, watch } from 'vue';
import HelpIcon from './HelpIcon.vue';
import AskForm from './AskForm.vue';
import Chip from '../../DesignSystem/Chip.vue';
import EmptyState from '../../DesignSystem/EmptyState.vue';

const props = defineProps({
    faqs: { type: Array, required: true },
    categories: { type: Array, required: true },
    email: { type: String, default: null },
});

const COLLAPSED = 4;
const search = ref('');
const category = ref('all');
const openId = ref(null);
const showAll = ref(false);

watch([search, category], () => { openId.value = null; });

const matching = computed(() => {
    const term = search.value.trim().toLowerCase();
    return props.faqs.filter((faq) => (category.value === 'all' || faq.category === category.value)
        && (term === '' || faq.question.toLowerCase().includes(term) || faq.answer.toLowerCase().includes(term)));
});
const filtering = computed(() => search.value.trim() !== '' || category.value !== 'all');
const visible = computed(() => (showAll.value || filtering.value ? matching.value : matching.value.slice(0, COLLAPSED)));
const hidden = computed(() => matching.value.length - visible.value.length);

const toggle = (id) => { openId.value = openId.value === id ? null : id; };
const filters = computed(() => [{ id: 'all', label: 'All' }, ...props.categories]);
</script>

<template>
    <div class="flex min-h-0 flex-1 flex-col">
        <div class="flex flex-none flex-col gap-3 border-b border-line bg-surface px-6 py-4">
            <label class="relative block">
                <span class="sr-only">Search questions</span>
                <HelpIcon name="search" :size="16" class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-muted" />
                <input v-model="search" type="search" placeholder="Search questions"
                       class="w-full rounded-[10px] border border-line bg-surface py-2.5 pr-3.5 pl-9 text-sm focus:border-navy focus:outline-none">
            </label>
            <div class="flex gap-2 overflow-x-auto pb-0.5" role="group" aria-label="Filter by topic">
                <button v-for="filter in filters" :key="filter.id" type="button" :aria-pressed="category === filter.id" @click="category = filter.id"
                        :class="['flex-none rounded-full border px-3 py-1.5 text-xs font-semibold transition-colors',
                                 category === filter.id ? 'border-navy bg-navy text-on-navy' : 'border-line bg-surface text-ink-2 hover:border-navy-400']">
                    {{ filter.label }}
                </button>
            </div>
        </div>

        <div class="flex flex-1 flex-col gap-2 overflow-y-auto px-6 py-5">
            <div v-for="faq in visible" :key="faq.id"
                 :class="['rounded-[12px] border bg-surface transition-colors', openId === faq.id ? 'border-navy-400' : 'border-line hover:border-navy-100']">
                <button type="button" :aria-expanded="openId === faq.id" @click="toggle(faq.id)"
                        class="flex w-full items-start justify-between gap-4 px-4 py-3.5 text-left">
                    <span class="font-semibold leading-snug">{{ faq.question }}</span>
                    <HelpIcon name="chevronDown" :size="16" :class="['mt-0.5 flex-none text-muted transition-transform', { 'rotate-180': openId === faq.id }]" />
                </button>
                <div v-if="openId === faq.id" class="flex flex-col items-start gap-3 px-4 pb-4">
                    <p class="leading-relaxed whitespace-pre-line text-ink-2">{{ faq.answer }}</p>
                    <Chip tone="neutral">{{ faq.categoryLabel }}</Chip>
                </div>
            </div>

            <EmptyState v-if="visible.length === 0" :title="search.trim() ? `Nothing matched “${search.trim()}”` : 'No questions in this topic'">
                Try a different word, or ask us directly below.
            </EmptyState>

            <button v-if="hidden > 0" type="button" class="self-center py-1 text-xs font-semibold text-muted hover:text-ink" @click="showAll = true">
                Show {{ hidden }} more {{ hidden === 1 ? 'question' : 'questions' }}
            </button>

            <AskForm :email="email" class="mt-4" />
        </div>
    </div>
</template>
