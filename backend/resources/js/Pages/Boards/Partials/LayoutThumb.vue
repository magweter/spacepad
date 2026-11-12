<script setup>
// The shape of a board's layout in miniature. Neutral blocks only: the list has no live status,
// so it never colours a room as free or in use.
defineProps({
    viewMode: { type: String, default: 'card' }, // card | table | grid
    theme: { type: String, default: 'dark' }, // dark | light | system
});
</script>

<template>
    <div class="aspect-[16/7] overflow-hidden rounded-md p-1.5" :class="theme === 'light' ? 'border border-line bg-surface' : 'bg-navy'">
        <div v-if="viewMode === 'table'" class="flex h-full flex-col gap-1">
            <span v-for="n in 5" :key="n" class="h-1.5 rounded-sm" :class="theme === 'light' ? 'bg-line' : 'bg-navy-700'"></span>
        </div>
        <div v-else class="grid h-full gap-1" :class="viewMode === 'grid' ? 'grid-cols-6 grid-rows-3' : 'grid-cols-4 grid-rows-2'">
            <span v-for="n in (viewMode === 'grid' ? 18 : 8)" :key="n" class="rounded-[3px]" :class="theme === 'light' ? 'bg-line' : 'bg-navy-700'"></span>
        </div>
    </div>
</template>
