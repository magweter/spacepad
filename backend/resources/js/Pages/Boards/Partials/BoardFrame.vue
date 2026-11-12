<script setup>
// The real board page in a 16:9 frame, rendered at `width` and scaled down to fit. A small card
// renders the page narrower, so the rooms stay readable at that size.
import { onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps({
    src: { type: String, required: true },
    title: { type: String, default: 'Board preview' },
    version: { type: [Number, String], default: null },
    width: { type: Number, default: 1920 },
});

const frame = ref(null);
const scale = ref(0.2);
let observer;
const fit = () => { if (frame.value) scale.value = frame.value.clientWidth / props.width; };
onMounted(() => {
    fit();
    observer = new ResizeObserver(fit);
    observer.observe(frame.value);
});
onBeforeUnmount(() => observer?.disconnect());
</script>

<template>
    <div ref="frame" class="relative aspect-video overflow-hidden rounded-md bg-navy">
        <iframe :key="version" :src="src" :title="title" tabindex="-1" loading="lazy"
                class="pointer-events-none absolute top-0 left-0 origin-top-left border-0"
                :style="{ width: `${width}px`, height: `${(width * 9) / 16}px`, transform: `scale(${scale})` }"></iframe>
    </div>
</template>
