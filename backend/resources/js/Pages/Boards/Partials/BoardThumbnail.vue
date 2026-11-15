<script setup>
// A sketch of a board for the overview: its theme, title, layout and the names of its rooms, drawn
// in the page itself. Unlike the live page in an iframe it costs nothing to show twenty of them.
// It shows no room status: the sketch has no calendar data, so every room is drawn the same.
import { computed } from 'vue';

const props = defineProps({
    title: { type: String, default: null },
    rooms: { type: Array, default: () => [] },
    roomCount: { type: Number, default: 0 },
    viewMode: { type: String, default: 'card' },
    theme: { type: String, default: 'dark' },
});

const light = computed(() => props.theme === 'light');
const shown = computed(() => props.rooms.slice(0, props.viewMode === 'table' ? 6 : props.viewMode === 'grid' ? 12 : 9));
const more = computed(() => Math.max(0, props.roomCount - shown.value.length));
const cols = computed(() => {
    if (props.viewMode === 'grid') return 4;
    const n = shown.value.length;
    return n <= 1 ? 1 : n <= 4 ? 2 : 3;
});
</script>

<template>
    <div class="flex aspect-video flex-col gap-[6%] overflow-hidden rounded-md p-[5%]"
         :class="light ? 'bg-ground text-ink' : 'bg-navy text-white'" aria-hidden="true">
        <div class="flex items-center justify-between gap-2">
            <span class="truncate text-[11px] font-bold tracking-tight">{{ title ?? '' }}</span>
            <span class="h-1.5 w-8 shrink-0 rounded-full" :class="light ? 'bg-line' : 'bg-navy-600'"></span>
        </div>

        <p v-if="!shown.length" class="m-auto text-[11px]" :class="light ? 'text-muted' : 'text-on-navy-muted'">No rooms on this board yet</p>

        <div v-else-if="viewMode === 'table'" class="flex min-h-0 flex-1 flex-col gap-[3%]">
            <div v-for="name in shown" :key="name" class="flex min-h-0 flex-1 items-center gap-2 rounded-[3px] px-1.5"
                 :class="light ? 'bg-surface' : 'bg-navy-800'">
                <span class="h-1.5 w-1.5 shrink-0 rounded-full" :class="light ? 'bg-line' : 'bg-navy-600'"></span>
                <span class="truncate text-[9px] font-semibold">{{ name }}</span>
                <span class="ml-auto h-1 w-1/4 shrink-0 rounded-full" :class="light ? 'bg-line-soft' : 'bg-navy-700'"></span>
            </div>
        </div>

        <div v-else class="grid min-h-0 flex-1 gap-[3%]" :style="{ gridTemplateColumns: `repeat(${cols}, minmax(0, 1fr))` }">
            <div v-for="name in shown" :key="name" class="flex min-h-0 min-w-0 flex-col justify-between gap-1 rounded-[3px] p-1.5"
                 :class="light ? 'bg-surface' : 'bg-navy-800'">
                <span class="truncate text-[9px] font-semibold">{{ name }}</span>
                <span class="h-1 w-2/3 rounded-full" :class="light ? 'bg-line-soft' : 'bg-navy-700'"></span>
            </div>
        </div>

        <span v-if="more" class="-mt-[3%] text-right text-[9px]" :class="light ? 'text-muted' : 'text-on-navy-muted'">+{{ more }} more</span>
    </div>
</template>
