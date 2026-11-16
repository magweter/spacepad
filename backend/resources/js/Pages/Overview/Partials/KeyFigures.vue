<script setup>
import { computed } from 'vue';

const props = defineProps({ figures: Object, accounts: Array });

// Every display that is not online is either offline or has no tablet, so the parts add up to the total.
function displaysSub(f) {
    if (f.withoutTablet === f.total) return 'No tablets paired yet';
    const parts = [];
    if (f.offline) parts.push(`${f.offline} offline`);
    if (f.withoutTablet) parts.push(`${f.withoutTablet} without a tablet`);
    return parts.length ? parts.join(', ') : 'All online';
}

const tiles = computed(() => {
    const f = props.figures;
    const readOnly = props.accounts.filter((a) => a.readOnly).length;
    return [
        { label: 'Displays online', value: f.online, of: f.total,
          sub: displaysSub(f) },
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
            <span class="flex items-baseline gap-1 font-display leading-none font-bold tabular-nums">
                <!-- Nothing to count yet reads as a dash, not as 0/0. -->
                <span class="text-[30px] tracking-[-0.03em]">{{ tile.of === 0 ? '-' : tile.value }}</span>
                <span v-if="tile.of" class="text-base text-muted">/ {{ tile.of }}</span>
            </span>
            <span class="text-xs text-muted">{{ tile.sub }}</span>
        </div>
      </div>
    </section>
</template>
