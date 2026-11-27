<script setup>
// One "Needs attention" item (design 01d): a coloured bar on the left, the title with its tag, one
// line on what happened and what to do, and one button. Inside AttentionList it is a row of the
// card; on its own (an alert popup, the admin preview) it draws its own card.
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import Chip from '../../DesignSystem/Chip.vue';
import Icon from '../Icon.vue';

const props = defineProps({
    tone: { type: String, default: 'amber' }, // a Chip tone: reserved | amber | neutral | info | available
    tag: { type: String, default: null },
    title: { type: String, required: true },
    body: { type: String, default: null },
    cta: { type: String, default: null },
    href: { type: String, default: null },
    external: { type: Boolean, default: false },
    standalone: { type: Boolean, default: false },
    dismissible: { type: Boolean, default: false },
});
const emit = defineEmits(['action', 'dismiss']);

const bar = {
    reserved: 'var(--status-reserved)', amber: 'var(--status-checkin)', neutral: 'var(--status-offline)',
    info: 'var(--info)', available: 'var(--status-available)', navy: 'var(--brand-navy)',
};
// The button of something that blocks the product is the primary one.
const primary = computed(() => props.tone === 'reserved' || props.tone === 'amber');
const button = computed(() => ['sp-btn sp-btn-md flex-none', primary.value ? 'sp-btn-primary' : 'sp-btn-secondary']);
</script>

<template>
    <div class="flex flex-wrap items-center gap-x-4 gap-y-2 border-l-[3px] bg-surface py-3.5 pr-5 pl-[17px]"
         :class="standalone ? 'rounded-xl border border-line-soft' : ''" :style="{ borderLeftColor: bar[tone] ?? bar.neutral }"
         :role="tone === 'reserved' ? 'alert' : 'status'">
        <span class="flex min-w-0 flex-[1_1_320px] flex-col gap-0.5">
            <span class="flex flex-wrap items-center gap-2">
                <span class="text-sm font-semibold text-navy">{{ title }}</span>
                <Chip v-if="tag" :tone="tone">{{ tag }}</Chip>
            </span>
            <span v-if="body" class="text-[13px] text-muted">{{ body }}</span>
        </span>
        <template v-if="cta">
            <a v-if="href && external" :href="href" :class="button">{{ cta }}</a>
            <Link v-else-if="href" :href="href" :class="button">{{ cta }}</Link>
            <button v-else type="button" :class="button" @click="emit('action')">{{ cta }}</button>
        </template>
        <button v-if="dismissible" type="button" :aria-label="`Dismiss: ${title}`" title="Dismiss"
                class="-mr-2 inline-flex h-8 w-8 flex-none items-center justify-center rounded-md text-muted hover:bg-ground hover:text-navy" @click="emit('dismiss')">
            <Icon name="close" :size="15" />
        </button>
    </div>
</template>
