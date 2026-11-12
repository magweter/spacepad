<script setup>
// The overview: every board as a card with a live preview of what its screen shows.
import Icon from '../../../Components/Icon.vue';
import Button from '../../../DesignSystem/Button.vue';
import BoardFrame from './BoardFrame.vue';

defineProps({
    boards: { type: Array, required: true },
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
    <section aria-label="Your boards" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
        <article v-for="b in boards" :key="b.id" class="sp-card overflow-hidden">
            <a :href="b.showUrl" target="_blank" rel="noopener" class="block bg-navy-800 p-2" :aria-label="`Open ${b.name}`">
                <BoardFrame :src="b.showUrl" :title="`Preview of ${b.name}`" :width="1024" />
            </a>
            <div class="flex flex-col gap-3 px-4 pb-4">
                <div class="flex flex-col gap-0.5">
                    <span class="font-semibold">{{ b.name }}</span>
                    <span class="text-xs text-muted">{{ meta(b) }}</span>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Button v-if="b.canUpdate" size="sm" :href="b.editUrl"><template #icon><Icon name="settings" :size="14" /></template>Settings</Button>
                    <Button variant="secondary" size="sm" :href="b.showUrl" external target="_blank" rel="noopener"><template #icon><Icon name="external" :size="14" /></template>Open</Button>
                </div>
            </div>
        </article>

        <a v-if="canCreate" :href="createUrl"
           class="flex min-h-[220px] items-center justify-center gap-2 rounded-lg border border-dashed border-offline-border text-[13px] font-semibold text-ink-2 hover:border-navy hover:text-navy">
            <Icon name="plus" :size="16" />Create a board
        </a>
    </section>
</template>
