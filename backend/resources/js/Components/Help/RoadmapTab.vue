<script setup>
// Roadmap tab: approved roadmap items with a vote toggle, and a form to suggest a feature.
import { reactive, watch } from 'vue';
import HelpIcon from './HelpIcon.vue';
import SuggestForm from './SuggestForm.vue';
import Chip from '../../DesignSystem/Chip.vue';
import EmptyState from '../../DesignSystem/EmptyState.vue';
import { helpUrls, requestJson } from './helpPanel.js';

const props = defineProps({ items: { type: Array, required: true } });

// Local copies so votes can update optimistically.
const state = reactive({ items: [] });
watch(() => props.items, (items) => { state.items = items.map((item) => ({ ...item, voting: false })); }, { immediate: true });

const tones = { building: 'amber', planned: 'info', considering: 'neutral', shipped: 'available' };

async function vote(item) {
    if (item.voting) return;
    const wasVoted = item.voted;
    const previousCount = item.votesCount;
    item.voting = true;
    item.voted = !wasVoted;
    item.votesCount += wasVoted ? -1 : 1;
    try {
        const result = await requestJson(helpUrls.vote(item.id), { method: 'POST' });
        if (!result.ok) throw new Error('Vote failed');
        item.voted = result.data.voted;
        item.votesCount = result.data.votes_count;
    } catch {
        item.voted = wasVoted;
        item.votesCount = previousCount;
    } finally {
        item.voting = false;
    }
}
</script>

<template>
    <div class="flex flex-1 flex-col gap-3 overflow-y-auto px-6 py-5">
        <article v-for="item in state.items" :key="item.id" class="flex items-start gap-3 rounded-[12px] border border-line bg-surface p-4">
            <button type="button" :aria-pressed="item.voted" :disabled="item.voting" @click="vote(item)"
                    :aria-label="item.voted ? `Remove your vote for ${item.title}` : `Vote for ${item.title}`"
                    :class="['flex w-11 flex-none flex-col items-center gap-0.5 rounded-[10px] border py-2 text-xs font-semibold transition-colors',
                             item.voted ? 'border-amber-300 bg-amber-50 text-navy' : 'border-line bg-surface text-ink-3 hover:border-navy-400 hover:text-ink']">
                <HelpIcon name="arrowUp" :size="14" :stroke="2.5" />
                <span>{{ item.votesCount }}</span>
            </button>
            <div class="flex min-w-0 flex-1 flex-col gap-1.5">
                <h3 class="font-semibold leading-snug">{{ item.title }}</h3>
                <p v-if="item.description" class="text-[13px] leading-relaxed whitespace-pre-line text-ink-3">{{ item.description }}</p>
                <div class="flex flex-wrap items-center gap-2">
                    <Chip :tone="tones[item.status] ?? 'neutral'">{{ item.statusLabel }}</Chip>
                    <span v-if="item.expectedAt" class="inline-flex items-center gap-1 text-xs text-muted">
                        <HelpIcon name="calendar" :size="12" />
                        Expected {{ item.expectedAt }}
                    </span>
                </div>
            </div>
        </article>

        <EmptyState v-if="state.items.length === 0" title="No roadmap items yet">Check back soon, or suggest one below.</EmptyState>

        <SuggestForm class="mt-4" />
    </div>
</template>
