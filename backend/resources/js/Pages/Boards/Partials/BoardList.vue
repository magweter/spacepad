<script setup>
// The workspace's boards as cards; the one in the editor has the navy ring.
import { Link } from '@inertiajs/vue3';
import Icon from '../../../Components/Icon.vue';
import LayoutThumb from './LayoutThumb.vue';

defineProps({
    boards: { type: Array, required: true },
    selectedId: { type: String, default: null },
    creating: { type: Boolean, default: false },
    canCreate: { type: Boolean, default: false },
    createUrl: { type: String, required: true },
});

const layout = { card: 'Card layout', table: 'Table layout', grid: 'Grid layout' };
const meta = (b) => [
    layout[b.viewMode] ?? 'Card layout',
    b.showAll ? 'all displays' : `${b.roomCount} ${b.roomCount === 1 ? 'room' : 'rooms'}`,
    b.isPublic ? 'public link on' : 'private',
].join(', ');
</script>

<template>
    <section aria-label="Your boards" class="flex flex-[0_1_300px] flex-col gap-2.5">
        <component :is="b.canUpdate ? Link : 'div'" v-for="b in boards" :key="b.id" :href="b.canUpdate ? b.editUrl : undefined" preserve-scroll
                   class="flex flex-col gap-2.5 rounded-lg border bg-surface p-3.5 transition-colors"
                   :class="[b.id === selectedId && !creating ? 'border-navy shadow-[0_0_0_1px_var(--brand-navy)]' : 'border-line', b.canUpdate ? 'hover:border-navy-400' : '']"
                   :aria-current="b.id === selectedId && !creating ? 'true' : null">
            <LayoutThumb :view-mode="b.viewMode" :theme="b.theme" />
            <div class="flex flex-col gap-0.5">
                <span class="font-semibold">{{ b.name }}</span>
                <span class="text-xs text-muted">{{ meta(b) }}</span>
            </div>
        </component>

        <Link v-if="canCreate" :href="createUrl" preserve-scroll
              class="flex items-center justify-center gap-2 rounded-lg border border-dashed p-[18px] text-[13px] font-semibold"
              :class="creating ? 'border-navy bg-surface text-navy' : 'border-offline-border text-ink-2 hover:border-navy hover:text-navy'">
            <Icon name="plus" :size="16" />Create a board
        </Link>
    </section>
</template>
