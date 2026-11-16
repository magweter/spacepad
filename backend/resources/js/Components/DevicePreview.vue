<script setup>
// A room display as it looks on the wall, drawn from tokens (design system: "product shots are
// drawn, not photographed"). The coloured frame is the status; scale keeps every size in step.
const props = defineProps({
    room: { type: String, default: 'Boardroom' },
    meta: { type: String, default: 'Floor 1, 12 seats' },
    time: { type: String, default: '09:48' },
    until: { type: String, default: 'Free until 11:00' },
    next: { type: Object, default: () => ({ time: '11:00', title: 'Design sync' }) },
    scale: { type: Number, default: 1 },
});

const px = (value) => `${(value * props.scale).toFixed(1)}px`;
</script>

<template>
    <div class="rounded-[17.6px] bg-console-ground shadow-[var(--shadow-device),inset_0_0_0_1.1px_var(--brand-navy-700)]" :style="{ padding: px(12), borderRadius: px(17.6) }">
        <div class="flex aspect-[16/10] flex-col overflow-hidden text-white"
             :style="{
                 borderRadius: px(5.6),
                 border: `${px(7.2)} solid var(--status-available)`,
                 padding: `${px(16)} ${px(19.2)} ${px(14.4)}`,
                 background: 'radial-gradient(70% 90% at 0% 100%, rgba(22, 163, 74, 0.42), transparent 70%), radial-gradient(55% 60% at 100% 0%, rgba(74, 222, 128, 0.16), transparent 70%), #0B1221',
             }">
            <div class="flex items-start justify-between">
                <span class="font-display leading-none font-bold tracking-tight tabular-nums" :style="{ fontSize: px(18.4) }">{{ time }}</span>
                <span class="flex flex-col text-right leading-tight font-semibold tracking-tight" :style="{ fontSize: px(12.8), gap: px(2.4) }">
                    {{ room }}<small class="font-medium text-slate-400" :style="{ fontSize: px(7.6) }">{{ meta }}</small>
                </span>
            </div>
            <div class="flex flex-1 flex-col items-start justify-center" :style="{ gap: px(4.8) }">
                <span class="leading-none font-bold tracking-tight" :style="{ fontSize: px(30.4) }">All yours!</span>
                <span class="text-slate-400" :style="{ fontSize: px(13.6) }">{{ until }}</span>
                <span class="border-[1.5px] border-[var(--glass-border)] bg-white/10 font-semibold shadow-[inset_0_1px_0_rgba(255,255,255,0.22)]"
                      :style="{ marginTop: px(8.8), padding: `${px(6.4)} ${px(12)}`, borderRadius: px(6.4), fontSize: px(10.4) }">Book now</span>
            </div>
            <div class="flex items-center justify-between border-[1.5px] border-[var(--glass-border)] bg-white/10 shadow-[inset_0_1px_0_rgba(255,255,255,0.22)]"
                 :style="{ height: px(32), padding: `0 ${px(10.4)}`, borderRadius: px(6.4), fontSize: px(10) }">
                <span v-if="next"><span class="text-slate-400 tabular-nums" :style="{ marginRight: px(8.8) }">{{ next.time }}</span><b>{{ next.title }}</b></span>
                <span v-else class="text-slate-400">Nothing else today</span>
                <span>View schedule</span>
            </div>
        </div>
    </div>
</template>
