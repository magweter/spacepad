<script setup>
// What a workspace with Pro looks like at a glance: displays in the office's own look, a board on
// the lobby TV, the team, and a booking made at the door. Drawn, with example rooms.
import MiniDisplay from './MiniDisplay.vue';

defineProps({ enterprise: { type: Boolean, default: false } });

const photos = {
    oak: 'linear-gradient(135deg, #8a6a4a 0%, #c9a77c 45%, #5d4630 100%)',
    glass: 'linear-gradient(160deg, #2c4a6b 0%, #7fa6c4 50%, #1d2f45 100%)',
    green: 'linear-gradient(140deg, #2f5d46 0%, #8fbf8a 55%, #1f3b2d 100%)',
};
const board = [
    { name: 'Boardroom', tone: 'reserved', word: 'In use' },
    { name: 'Focus 1', tone: 'available', word: 'Free' },
    { name: 'Atlas', tone: 'checkin', word: 'Check-in' },
    { name: 'Studio', tone: 'available', word: 'Free' },
    { name: 'Lounge', tone: 'reserved', word: 'In use' },
];
const ground = 'radial-gradient(70% 80% at 100% 0%, color-mix(in srgb, var(--brand-amber) 20%, transparent), transparent 70%), radial-gradient(60% 70% at 0% 100%, color-mix(in srgb, var(--status-available) 18%, transparent), transparent 70%), var(--brand-navy)';
</script>

<template>
    <div class="relative aspect-[16/11] w-full overflow-hidden rounded-xl" :style="{ background: ground }" aria-hidden="true">
        <!-- Board on the lobby TV -->
        <div class="absolute top-[7%] right-[5%] w-[46%] rounded-lg border-[5px] border-navy-700 bg-navy-800 p-2.5 shadow-[0_18px_40px_rgba(0,0,0,0.35)]">
            <div class="mb-1.5 flex items-center justify-between text-[8px] font-semibold text-white">
                <span>{{ enterprise ? 'Amsterdam, floor 1 and 2' : 'Meeting rooms, floor 1' }}</span><span class="text-white/60 tabular-nums">09:48</span>
            </div>
            <div class="flex flex-col gap-1">
                <span v-for="r in board" :key="r.name" class="flex items-center justify-between rounded-[3px] border-l-2 bg-white/5 px-1.5 py-1 text-[7.5px] text-white"
                      :style="{ borderColor: `var(--status-${r.tone})` }">
                    {{ r.name }}<span class="font-semibold" :style="{ color: `var(--status-${r.tone})` }">{{ r.word }}</span>
                </span>
            </div>
        </div>

        <!-- Displays in the office's own look -->
        <div class="absolute top-[10%] left-[5%] w-[40%] -rotate-2">
            <MiniDisplay room="Boardroom" title="Q4 planning" detail="Until 10:30" tone="reserved" :photo="photos.oak" button="End now" />
        </div>
        <div class="absolute bottom-[9%] left-[13%] w-[38%] rotate-1">
            <MiniDisplay room="Focus 1" title="All yours!" detail="Free until 11:00" tone="available" :photo="photos.green" button="Book now" />
        </div>
        <div v-if="enterprise" class="absolute bottom-[6%] left-[3%] w-[22%] -rotate-3 opacity-90">
            <MiniDisplay room="Atlas" title="Check in" detail="Starts now" tone="checkin" :photo="photos.glass" />
        </div>

        <!-- A booking just made at the door -->
        <div class="absolute top-[52%] left-[37%] flex items-center gap-2 rounded-lg bg-surface px-2.5 py-1.5 text-[9px] text-ink shadow-lg">
            <span class="flex h-4 w-4 items-center justify-center rounded-full bg-available-bg text-available-text">
                <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5" /></svg>
            </span>
            <span class="flex flex-col leading-tight"><b class="font-semibold">Focus 1 booked</b><span class="text-muted">30 min, from the tablet</span></span>
        </div>

        <!-- The team -->
        <div class="absolute right-[6%] bottom-[9%] flex items-center gap-2 rounded-full bg-white/10 py-1 pr-3 pl-1 backdrop-blur-sm">
            <span class="flex -space-x-1.5">
                <span v-for="(n, i) in ['MW', 'SV', 'LB', 'JK']" :key="n" class="flex h-6 w-6 items-center justify-center rounded-full border-2 border-navy text-[8px] font-bold"
                      :class="i === 0 ? 'bg-amber text-navy' : 'bg-navy-50 text-navy'">{{ n }}</span>
            </span>
            <span class="text-[9px] font-semibold text-white">{{ enterprise ? 'Every office, one portal' : '4 colleagues' }}</span>
        </div>
    </div>
</template>
