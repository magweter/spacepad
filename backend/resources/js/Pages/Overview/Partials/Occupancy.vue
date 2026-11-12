<script setup>
defineProps({ hours: Array, labels: Array });
const fill = { past: 'bg-navy', now: 'bg-amber', later: 'bg-line' };
</script>

<template>
    <div class="sp-card flex flex-col gap-3 px-5 py-[18px]">
        <div class="flex flex-wrap items-baseline justify-between gap-2">
            <h2 class="font-display text-base font-bold tracking-tight">Occupancy today</h2>
            <span class="text-xs text-muted">Share of rooms in use per hour, booked so far</span>
        </div>
        <div class="flex h-[72px] items-end gap-1.5">
            <div v-for="h in hours" :key="h.hour" class="flex h-full flex-1 flex-col justify-end" :title="`${String(h.hour).padStart(2, '0')}:00, ${Math.round(h.share * 100)}%`">
                <div class="w-full rounded-t" :class="fill[h.when]" :style="{ height: `${Math.max(3, h.share * 100)}%` }"></div>
            </div>
        </div>
        <div class="flex justify-between text-[11px] text-muted"><span v-for="l in labels" :key="l">{{ l }}</span></div>
    </div>
</template>
