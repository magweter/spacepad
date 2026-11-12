<script setup>
import { computed } from 'vue';

const props = defineProps({ figures: Object, accounts: Array });

const tiles = computed(() => {
    const f = props.figures;
    const readOnly = props.accounts.filter((a) => a.readOnly).length;
    return [
        { label: 'Displays online', value: f.online, of: f.total,
          sub: f.withoutTablet === f.total ? 'No tablets paired yet' : f.offline ? `${f.offline} offline` : f.withoutTablet ? `${f.withoutTablet} without a tablet` : 'All online' },
        { label: 'Rooms free now', value: f.free, of: f.live, sub: f.live ? `${f.live - f.free} in use` : 'Waiting for the tablets' },
        { label: 'Bookings today', value: f.bookings, sub: f.fromTablet ? `${f.fromTablet} from a tablet` : 'None from a tablet yet' },
        { label: 'Calendars connected', value: props.accounts.length, sub: !props.accounts.length ? 'None yet' : readOnly ? `${readOnly} read only` : 'Read and book' },
    ];
});
</script>

<template>
    <section aria-label="Key figures" class="sp-card">
      <div class="grid grid-cols-2 py-1 lg:grid-cols-4">
        <div v-for="(tile, i) in tiles" :key="tile.label" class="flex flex-col gap-1 px-5 py-4" :class="i < tiles.length - 1 ? 'lg:border-r lg:border-line-soft' : ''">
            <span class="text-[13px] font-medium text-muted">{{ tile.label }}</span>
            <span class="font-display text-[30px] leading-none font-bold tracking-[-0.03em] tabular-nums">{{ tile.value }}<span v-if="tile.of !== undefined" class="ml-1 text-base text-muted">/{{ tile.of }}</span></span>
            <span class="text-xs text-muted">{{ tile.sub }}</span>
        </div>
      </div>
    </section>
</template>
