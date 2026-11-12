<script setup>
// A room display drawn the way the app shows it. Without a photo it is the glass theme: a dark
// ground with a soft glow in the status colour inside the status frame. With a photo it is a
// customised display: the photo dimmed behind the text, the organisation's logo top left.
// `size` is the width it is drawn at; everything scales with it.
import { computed } from 'vue';

const props = defineProps({
    size: { type: Number, default: 320 },
    tone: { type: String, default: 'available' }, // available | reserved
    room: { type: String, default: 'Boardroom' },
    subline: { type: String, default: '' },
    title: { type: String, default: 'All yours!' },
    detail: { type: String, default: 'Free until 11:00' },
    buttons: { type: Array, default: () => ['Book now'] },
    next: { type: String, default: 'Design sync' },
    nextTime: { type: String, default: '11:00' },
    time: { type: String, default: '09:48' },
    photo: { type: String, default: null },
    logo: { type: Boolean, default: false },
});

const k = computed(() => props.size / 320);
const px = (v) => `${(v * k.value).toFixed(2)}px`;
const frame = computed(() => `var(--status-${props.tone})`);
const ground = computed(() => (props.photo
    ? `linear-gradient(90deg, rgba(8, 12, 22, 0.82), rgba(8, 12, 22, 0.35)), url(${props.photo}) center / cover`
    : `radial-gradient(70% 90% at 0% 100%, color-mix(in srgb, ${frame.value} 42%, transparent), transparent 70%), radial-gradient(55% 60% at 100% 0%, color-mix(in srgb, ${frame.value} 16%, transparent), transparent 70%), #0B1221`));
</script>

<template>
    <div class="bg-console-ground" :style="{ width: px(320), padding: px(9), borderRadius: px(16), boxShadow: '0 20px 44px rgba(0, 0, 0, 0.35), inset 0 0 0 1px var(--brand-navy-700)' }">
        <div class="flex aspect-[16/10] flex-col overflow-hidden text-white"
             :style="{ borderRadius: px(5), border: `${px(6)} solid ${frame}`, padding: `${px(13)} ${px(15)} ${px(11)}`, background: ground }">
            <div class="flex items-start justify-between">
                <span v-if="logo" class="inline-flex items-center font-semibold tracking-tight" :style="{ gap: px(5), fontSize: px(11) }">
                    <svg :width="px(16)" :height="px(16)" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m3 20 6-11 4 7 3-5 5 9z" /><circle cx="17" cy="6" r="2" /></svg>
                    Northpeak
                </span>
                <span v-else class="font-display leading-none font-bold tracking-tight tabular-nums" :style="{ fontSize: px(15) }">{{ time }}</span>
                <span class="flex flex-col text-right leading-tight font-semibold tracking-tight" :style="{ fontSize: px(10.5), gap: px(2) }">
                    {{ room }}<small v-if="subline" class="font-medium text-white/60" :style="{ fontSize: px(6.5) }">{{ subline }}</small>
                </span>
            </div>
            <div class="flex flex-1 flex-col items-start justify-center" :style="{ gap: px(4) }">
                <span v-if="logo" class="font-display leading-none font-bold tracking-tight tabular-nums text-white/70" :style="{ fontSize: px(9) }">{{ time }}</span>
                <span class="font-display leading-none font-bold tracking-tight" :style="{ fontSize: px(25) }">{{ title }}</span>
                <span class="text-white/70" :style="{ fontSize: px(10.5) }">{{ detail }}</span>
                <span class="flex" :style="{ gap: px(5), marginTop: px(6) }">
                    <span v-for="b in buttons" :key="b" class="border-[1.5px] border-[var(--glass-border)] bg-white/12 font-semibold shadow-[inset_0_1px_0_rgba(255,255,255,0.22)] backdrop-blur-sm"
                          :style="{ padding: `${px(5)} ${px(9)}`, borderRadius: px(5), fontSize: px(8.5) }">{{ b }}</span>
                </span>
            </div>
            <div class="flex items-center justify-between border-[1.5px] border-[var(--glass-border)] bg-white/10 shadow-[inset_0_1px_0_rgba(255,255,255,0.22)] backdrop-blur-sm"
                 :style="{ height: px(26), padding: `0 ${px(9)}`, borderRadius: px(5), fontSize: px(8) }">
                <span><span class="text-white/60 tabular-nums" :style="{ marginRight: px(7) }">{{ nextTime }}</span><b>{{ next }}</b></span>
                <span>View schedule</span>
            </div>
        </div>
    </div>
</template>
