<script setup>
// The right half of the sign-in page (design frames 09a and 09b): a light panel with a dot grid and
// a tablet that cycles through what Spacepad shows. Free, in use, the day's calendar, a board and a
// branded display (own logo and background photo), every 3 seconds. With reduced motion it stays on the first one.
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

defineProps({
    headline: { type: String, required: true },
    lead: { type: String, required: true },
    line: { type: String, required: true },
    usps: { type: Array, default: () => [] },
});

const states = [
    { key: 'free', label: 'Available', dot: 'var(--status-available)', tint: 'color-mix(in srgb, var(--status-available) 24%, white)' },
    { key: 'busy', label: 'In use', dot: 'var(--status-reserved)', tint: 'color-mix(in srgb, var(--status-reserved) 20%, white)' },
    { key: 'calendar', label: 'Calendar', dot: 'var(--status-available)', tint: 'color-mix(in srgb, var(--status-available) 24%, white)' },
    { key: 'board', label: 'Board', dot: 'var(--brand-navy)', tint: 'var(--brand-navy-100)' },
    { key: 'brand', label: 'Your brand', dot: 'var(--brand-amber)', tint: 'color-mix(in srgb, var(--brand-amber) 38%, white)' },
];

const index = ref(0);
const clock = ref('');
const state = computed(() => states[index.value]);
const display = computed(() => (state.value.key === 'busy'
    ? { color: 'var(--status-reserved)', glow: 'rgba(220, 38, 38, 0.40)', word: 'In use', sub: 'Design sync until 11:00', button: 'End early' }
    : { color: 'var(--status-available)', glow: 'rgba(22, 163, 74, 0.42)', word: 'Available', sub: 'Free until 11:00', button: 'Book now' }));

const today = [
    { time: '09:00', title: 'Standup', free: true, past: true },
    { time: '11:00', title: 'Design sync' },
    { time: '13:00', title: 'Client call' },
    { time: '15:00', title: 'Retro' },
    { time: '16:00', title: 'Free', free: true },
];
const boardRooms = [
    { name: 'Boardroom', seats: 12, status: 'In use', detail: 'until 10:30', color: '#F87171' },
    { name: 'Focus 1', seats: 4, status: 'Free', detail: '1 h 12', color: '#4ADE80' },
    { name: 'Focus 2', seats: 4, status: 'Check-in', detail: '3 min left', color: '#FBBF24' },
    { name: 'Atrium', seats: 20, status: 'Free', detail: 'all afternoon', color: '#4ADE80' },
    { name: 'Workshop', seats: 16, status: 'In use', detail: 'until 12:00', color: '#F87171' },
    { name: 'Quiet room', seats: 2, status: 'Free', detail: '4 h', color: '#4ADE80' },
];

const now = () => {
    const d = new Date();
    return `${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`;
};

let timer;
onMounted(() => {
    clock.value = now();
    const still = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    let tick = 0;
    timer = setInterval(() => {
        clock.value = now();
        if (!still && ++tick % 3 === 0) index.value = (index.value + 1) % states.length;
    }, 1000);
});
onBeforeUnmount(() => clearInterval(timer));

const glass = 'border-[1.5px] border-white/20 bg-white/10 shadow-[inset_0_1px_0_rgba(255,255,255,0.22)]';
</script>

<template>
    <aside aria-hidden="true" class="relative flex min-h-[640px] min-w-0 flex-col justify-between gap-8 overflow-hidden rounded-3xl bg-ground p-14 text-navy">
        <div class="absolute inset-0 bg-[radial-gradient(circle,#C9CFDA_1px,transparent_1px)] bg-[length:24px_24px]"></div>
        <div class="absolute -top-[150px] -right-[150px] h-[520px] w-[520px] rounded-full transition-colors duration-700" :style="{ background: state.tint }"></div>
        <div class="absolute -bottom-[170px] -left-[170px] h-[380px] w-[380px] rounded-full" style="background: color-mix(in srgb, var(--brand-amber) 30%, white)"></div>

        <div class="relative z-[1] flex max-w-[400px] flex-col gap-2.5">
            <h2 class="font-display text-[30px] leading-[1.15] font-bold tracking-tight">{{ headline }}</h2>
            <p class="text-sm text-ink-3">{{ lead }}</p>
        </div>

        <div class="relative z-[1] flex flex-col items-center gap-[22px]">
            <!-- The tablet -->
            <div class="w-[400px] max-w-full rounded-2xl bg-console-ground p-[11px] shadow-[0_24px_48px_rgba(20,33,61,0.18),inset_0_0_0_1px_var(--brand-navy-700)]">
                <!-- Free or in use -->
                <div v-if="state.key === 'free' || state.key === 'busy'" class="flex aspect-[16/10] flex-col overflow-hidden rounded-md border-[7px] px-[17px] pt-3.5 pb-3 text-white transition-[border-color,background] duration-700"
                     :style="{ borderColor: display.color, background: `radial-gradient(70% 90% at 0% 100%, ${display.glow}, transparent 70%), radial-gradient(55% 60% at 100% 0%, rgba(74, 222, 128, 0.16), transparent 70%), #0B1221` }">
                    <div class="flex items-start justify-between">
                        <span class="font-display text-base leading-none font-bold tracking-tight tabular-nums">{{ clock }}</span>
                        <span class="flex flex-col gap-0.5 text-right text-[11px] leading-tight font-semibold">Boardroom<small class="text-[6.5px] font-medium text-slate-400">Floor 1, 12 seats</small></span>
                    </div>
                    <div class="flex flex-1 flex-col items-start justify-center gap-1">
                        <span class="text-[27px] leading-none font-bold tracking-tight">{{ display.word }}</span>
                        <span class="text-xs text-slate-400">{{ display.sub }}</span>
                        <span class="mt-[7px] rounded-md px-2.5 py-1.5 text-[9px] font-semibold" :class="glass">{{ display.button }}</span>
                    </div>
                    <div class="flex h-7 items-center justify-between rounded-md px-[9px] text-[9px]" :class="glass">
                        <span><span class="mr-2 text-slate-400 tabular-nums">11:00</span><b>Design sync</b></span>
                        <span>View schedule</span>
                    </div>
                </div>

                <!-- The day's calendar beside the status -->
                <div v-else-if="state.key === 'calendar'" class="flex aspect-[16/10] gap-3.5 overflow-hidden rounded-md border-[7px] border-[var(--status-available)] px-[17px] pt-3.5 pb-3 text-white"
                     style="background: radial-gradient(70% 90% at 0% 100%, rgba(22, 163, 74, 0.42), transparent 70%), radial-gradient(55% 60% at 100% 0%, rgba(74, 222, 128, 0.16), transparent 70%), #0B1221">
                    <div class="flex min-w-0 flex-1 flex-col">
                        <span class="font-display text-base leading-none font-bold tracking-tight tabular-nums">{{ clock }}</span>
                        <div class="flex flex-1 flex-col items-start justify-center gap-1">
                            <span class="text-[10px] font-semibold">Boardroom</span>
                            <span class="text-[27px] leading-none font-bold tracking-tight">Available</span>
                            <span class="text-xs text-slate-400">Free until 11:00</span>
                            <span class="mt-[7px] flex gap-1">
                                <span class="rounded-md px-2.5 py-1.5 text-[9px] font-semibold" :class="glass">Book now</span>
                                <span class="rounded-md px-2.5 py-1.5 text-[9px] font-semibold" :class="glass">30 min</span>
                            </span>
                        </div>
                    </div>
                    <div class="flex flex-[0_0_128px] flex-col gap-1.5 rounded-lg px-2.5 py-[9px]" :class="glass">
                        <span class="flex justify-between text-[7.5px] text-slate-400"><b class="text-white">Today</b>4 meetings</span>
                        <span v-for="m in today" :key="m.time" class="grid grid-cols-[3px_26px_1fr] items-center gap-1.5 text-[7.5px] leading-tight" :class="m.past ? 'opacity-50' : ''">
                            <span class="h-3.5 rounded-sm" :class="m.free ? 'bg-[#4ADE80]' : 'bg-[#F87171]'"></span>
                            <span class="text-slate-400 tabular-nums">{{ m.time }}</span>
                            <span class="truncate font-semibold">{{ m.title }}</span>
                        </span>
                    </div>
                </div>

                <!-- A board: many rooms at once -->
                <div v-else-if="state.key === 'board'" class="flex aspect-[16/10] flex-col gap-3.5 overflow-hidden rounded-md px-[18px] pt-4 pb-[13px] text-slate-50"
                     style="background: radial-gradient(60% 70% at 100% 0%, rgba(74, 222, 128, 0.14), transparent 70%), #0B1220">
                    <div class="flex items-center justify-between">
                        <span class="flex items-center gap-2">
                            <span class="inline-flex h-5 w-5 items-center justify-center rounded-[5px] bg-slate-50 text-[10px] font-bold text-[#0B1220]">A</span>
                            <span class="flex flex-col leading-tight"><span class="font-display text-[13px] font-bold tracking-tight">Acme HQ, Amsterdam</span><span class="text-[8px] text-slate-400">7 of 12 rooms free</span></span>
                        </span>
                        <span class="font-display text-2xl leading-none font-bold tracking-tight tabular-nums">{{ clock }}</span>
                    </div>
                    <div class="grid flex-1 grid-cols-2 content-start gap-x-2.5 gap-y-[9px]">
                        <div v-for="r in boardRooms" :key="r.name" class="grid grid-cols-[4px_1fr_auto] items-center gap-[9px] rounded-lg border border-white/15 bg-white/[0.08] py-2.5 pr-3 pl-2.5">
                            <span class="h-[26px] rounded-sm" :style="{ background: r.color }"></span>
                            <span class="flex min-w-0 flex-col leading-tight"><span class="text-[11px] font-semibold whitespace-nowrap">{{ r.name }}</span><span class="text-[8px] text-slate-400">{{ r.seats }} seats</span></span>
                            <span class="flex flex-col items-end leading-tight"><span class="text-[10px] font-bold" :style="{ color: r.color }">{{ r.status }}</span><span class="text-[8px] whitespace-nowrap text-slate-400">{{ r.detail }}</span></span>
                        </div>
                    </div>
                    <span class="inline-flex items-center gap-1.5 text-[7.5px] text-slate-400"><span class="h-[5px] w-[5px] rounded-full bg-[#4ADE80]"></span>Live, synced with Microsoft 365</span>
                </div>

                <!-- Branded: own logo and background -->
                <div v-else class="relative flex aspect-[16/10] flex-col overflow-hidden rounded-md border-[7px] border-[var(--status-available)] px-[17px] pt-3.5 pb-3 text-white"
                     style="background: url('/images/upgrade/display-background.jpg') center / cover">
                    <div class="absolute inset-0" style="background: radial-gradient(70% 90% at 0% 100%, rgba(22, 163, 74, 0.42), transparent 70%), linear-gradient(90deg, rgba(8, 14, 24, 0.88) 0%, rgba(8, 14, 24, 0.6) 55%, rgba(8, 14, 24, 0.25) 100%)"></div>
                    <div class="relative flex items-start justify-between">
                        <span class="flex items-center gap-[7px]">
                            <span class="font-display text-[19px] leading-none font-bold tracking-tight tabular-nums">{{ clock }}</span>
                            <span class="h-[13px] w-px bg-white/25"></span>
                            <span class="inline-flex items-center gap-1">
                                <span class="inline-flex h-[13px] w-[13px] items-center justify-center rounded-full bg-[#C9A96E]"><span class="h-[5px] w-[5px] rotate-45 rounded-[1px] bg-white"></span></span>
                                <span class="font-display text-[8px] font-bold tracking-[0.12em]">ACME</span>
                            </span>
                        </span>
                        <span class="flex flex-col gap-0.5 text-right text-[11px] leading-tight font-semibold">Boardroom<small class="text-[6.5px] font-medium text-slate-400">Floor 1, 12 seats</small></span>
                    </div>
                    <div class="relative flex flex-1 flex-col items-start justify-center gap-[3px]">
                        <span class="text-[30px] leading-none font-bold tracking-tight">Available</span>
                        <span class="text-xs text-slate-400">Free until 11:00</span>
                        <span class="mt-1.5 flex gap-1">
                            <span v-for="b in ['Book now', '15 min', '30 min']" :key="b" class="inline-flex h-6 items-center rounded-md px-3 text-[9px] font-semibold" :class="glass">{{ b }}</span>
                        </span>
                    </div>
                    <div class="relative flex h-7 items-center justify-between rounded-md px-[9px] text-[9px]" :class="glass">
                        <span><span class="mr-2 text-slate-400 tabular-nums">11:00</span><b>Design sync</b></span>
                        <span>View schedule</span>
                    </div>
                </div>
            </div>

            <!-- Which one is showing -->
            <div class="flex flex-wrap justify-center gap-5">
                <span v-for="(s, i) in states" :key="s.key" class="inline-flex items-center gap-2 text-[13px] font-semibold transition-colors duration-700"
                      :class="i === index ? 'text-navy' : 'text-offline'">
                    <span class="h-2 w-2 rounded-full transition-opacity duration-700" :class="i === index ? 'opacity-100' : 'opacity-35'" :style="{ background: s.dot }"></span>{{ s.label }}
                </span>
            </div>
        </div>

        <div class="relative z-[1] flex flex-col gap-3">
            <div v-if="usps.length" class="flex flex-wrap gap-2">
                <span v-for="u in usps" :key="u" class="inline-flex items-center gap-1.5 rounded-lg border-[1.5px] border-navy-100 bg-white px-2.5 py-1 text-xs font-semibold text-navy">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5" /></svg>{{ u }}
                </span>
            </div>
            <p class="min-h-[42px] max-w-[440px] text-sm text-ink-3">{{ line }}</p>
        </div>
    </aside>
</template>
