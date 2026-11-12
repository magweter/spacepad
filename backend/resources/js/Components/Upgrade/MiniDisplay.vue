<script setup>
// A small room display with its own background photo and logo, laid out as the app does: the
// status frame, clock and room name, the title, and a button.
defineProps({
    room: { type: String, required: true },
    title: { type: String, required: true },
    detail: { type: String, required: true },
    tone: { type: String, default: 'available' },
    logo: { type: String, default: 'ACME' },
    photo: { type: String, required: true }, // a CSS background standing in for the uploaded photo
    button: { type: String, default: null },
});
</script>

<template>
    <div class="rounded-[10px] bg-navy p-1.5 shadow-[0_18px_40px_rgba(0,0,0,0.35)]">
        <div class="relative flex aspect-[16/10] flex-col overflow-hidden rounded-[4px] text-white"
             :style="{ border: `4px solid var(--status-${tone})`, background: photo }">
            <div class="absolute inset-0 bg-black/45"></div>
            <div class="relative flex flex-1 flex-col px-2.5 pt-2 pb-2">
                <div class="flex items-start justify-between text-[8px] font-semibold">
                    <span class="rounded-[3px] bg-white px-1 py-px text-[7px] font-bold tracking-wide text-navy">{{ logo }}</span>
                    <span>{{ room }}</span>
                </div>
                <div class="flex flex-1 flex-col justify-center gap-0.5">
                    <span class="font-display text-[15px] leading-none font-bold">{{ title }}</span>
                    <span class="text-[8px] text-white/75">{{ detail }}</span>
                </div>
                <span v-if="button" class="self-start rounded-[4px] bg-white/20 px-1.5 py-0.5 text-[7px] font-semibold">{{ button }}</span>
            </div>
        </div>
    </div>
</template>
