<script setup>
// The saved board itself, live, in a TV frame. It is the real board page scaled down, so it shows
// what the lobby screen shows, not the unsaved changes in the editor.
import { onBeforeUnmount, onMounted, ref } from 'vue';

defineProps({ src: { type: String, required: true }, version: { type: [Number, String], default: null } });

const frame = ref(null);
const scale = ref(0.4);
let observer;
onMounted(() => {
    observer = new ResizeObserver(([entry]) => { scale.value = entry.contentRect.width / 1920; });
    observer.observe(frame.value);
});
onBeforeUnmount(() => observer?.disconnect());
</script>

<template>
    <div class="flex flex-col gap-2.5">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="text-[15px] font-semibold">Preview</h2>
            <span class="text-xs text-muted">1920 × 1080, live data, as last saved</span>
        </div>
        <div class="rounded-[14px] bg-navy-800 p-2.5 shadow-[var(--shadow-device)]">
            <div ref="frame" class="relative aspect-video overflow-hidden rounded-md bg-navy">
                <iframe :key="version" :src="src" title="Board preview" tabindex="-1" loading="lazy"
                        class="pointer-events-none absolute top-0 left-0 h-[1080px] w-[1920px] origin-top-left border-0"
                        :style="{ transform: `scale(${scale})` }"></iframe>
            </div>
        </div>
    </div>
</template>
