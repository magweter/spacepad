<script setup>
// A small drawing per Pro feature, in the portal's own tokens, so the dialog shows what changes
// rather than only saying it.
import DisplayGlyph from '../DisplayGlyph.vue';

defineProps({ feature: { type: String, required: true } });

const ground = 'radial-gradient(80% 90% at 100% 0%, color-mix(in srgb, var(--brand-amber) 18%, transparent), transparent 70%), var(--brand-navy)';

const rooms = [
    { name: 'Boardroom', tone: 'reserved', word: 'In use' },
    { name: 'Focus 1', tone: 'available', word: 'Free' },
    { name: 'Atlas', tone: 'checkin', word: 'Check-in' },
    { name: 'Focus 2', tone: 'available', word: 'Free' },
];
</script>

<template>
    <div class="flex aspect-[16/10] items-center justify-center overflow-hidden rounded-xl p-6" :style="{ background: ground }">
        <!-- Every room its own display -->
        <div v-if="feature === 'displays'" class="grid grid-cols-3 gap-3">
            <div v-for="(t, i) in ['reserved', 'available', 'checkin', 'available', 'available', 'reserved']" :key="i" class="scale-[1.6] p-2">
                <DisplayGlyph :tone="t" />
            </div>
        </div>

        <!-- One display, made yours -->
        <div v-else-if="feature === 'configure'" class="flex w-[78%] flex-col gap-2 rounded-lg border-[5px] border-available bg-black p-4 text-white">
            <span class="flex items-center justify-between text-[10px] text-white/70"><span class="rounded bg-amber px-1.5 py-0.5 text-[9px] font-bold text-navy">ACME</span>Boardroom</span>
            <span class="font-display text-2xl leading-none font-bold">All yours!</span>
            <span class="text-[11px] text-white/60">Free until 11:00</span>
            <span class="flex gap-1.5">
                <span class="rounded-md bg-white/20 px-2 py-1 text-[10px] font-semibold">Book now</span>
                <span class="rounded-md bg-white/20 px-2 py-1 text-[10px] font-semibold">Check in</span>
            </span>
        </div>

        <!-- Every room on one screen -->
        <div v-else-if="feature === 'boards'" class="flex w-[86%] flex-col gap-1.5 rounded-lg border-4 border-navy-700 bg-navy-800 p-3">
            <span class="mb-1 text-[11px] font-semibold text-white">Meeting rooms, floor 1</span>
            <span v-for="r in rooms" :key="r.name" class="flex items-center justify-between rounded border-l-[3px] bg-white/5 px-2 py-1.5 text-[10px] text-white"
                  :style="{ borderColor: `var(--status-${r.tone})` }">
                {{ r.name }}<span class="font-semibold" :style="{ color: `var(--status-${r.tone})` }">{{ r.word }}</span>
            </span>
        </div>

        <!-- One profile, many displays -->
        <div v-else-if="feature === 'profiles'" class="flex items-center gap-5">
            <div class="flex w-28 flex-col gap-1.5 rounded-lg bg-surface p-3 text-[10px] text-ink shadow-lg">
                <span class="font-semibold">Meeting rooms</span>
                <span class="flex items-center justify-between text-muted">Check-in <span class="h-2.5 w-4.5 rounded-full bg-navy"></span></span>
                <span class="flex items-center justify-between text-muted">Booking <span class="h-2.5 w-4.5 rounded-full bg-navy"></span></span>
                <span class="flex items-center justify-between text-muted">Logo <span class="rounded bg-amber px-1 text-[8px] font-bold text-navy">ACME</span></span>
            </div>
            <svg width="34" height="70" viewBox="0 0 34 70" fill="none" stroke="var(--brand-amber)" stroke-width="2" stroke-linecap="round" aria-hidden="true">
                <path d="M2 35h12M14 35 32 8M14 35h18M14 35l18 27" />
            </svg>
            <div class="flex flex-col gap-2">
                <DisplayGlyph tone="available" class="scale-[1.3]" />
                <DisplayGlyph tone="reserved" class="scale-[1.3]" />
                <DisplayGlyph tone="available" class="scale-[1.3]" />
            </div>
        </div>

        <!-- The whole team in one workspace -->
        <div v-else class="flex flex-col items-center gap-4">
            <div class="flex -space-x-3">
                <span v-for="(n, i) in ['MW', 'SV', 'LB', 'JK']" :key="n" class="flex h-14 w-14 items-center justify-center rounded-full border-[3px] border-navy text-base font-bold"
                      :class="i === 0 ? 'bg-amber text-navy' : 'bg-navy-50 text-navy'">{{ n }}</span>
            </div>
            <span class="rounded-full bg-white/10 px-3 py-1 text-[11px] text-white">Same displays, boards and calendars for everyone</span>
        </div>
    </div>
</template>
