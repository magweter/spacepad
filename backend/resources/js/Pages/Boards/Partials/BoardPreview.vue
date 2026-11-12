<script setup>
// The saved board itself, live, in a TV frame. It is the real board page scaled down, so it shows
// what the lobby screen shows, not the unsaved changes in the editor.
import BoardFrame from './BoardFrame.vue';

defineProps({ src: { type: String, required: true }, version: { type: [Number, String], default: null } });
</script>

<template>
    <div class="flex flex-col gap-2.5">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="text-[15px] font-semibold">Preview</h2>
            <span class="text-xs text-muted">1920 × 1080, live data, as last saved</span>
        </div>
        <div class="rounded-[14px] bg-navy-800 p-2.5 shadow-[var(--shadow-device)]">
            <BoardFrame :src="src" :version="version" />
        </div>
    </div>
</template>
