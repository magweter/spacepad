<script setup>
// What a Pro feature looks like in practice, drawn with realistic displays and a real board. The
// example organisation is made up; the photo is one of the bundled backgrounds.
import RealDisplay from './RealDisplay.vue';
import RealBoard from './RealBoard.vue';

defineProps({ feature: { type: String, required: true } });

const photo = '/images/upgrade/display-background.jpg';
const ground = 'radial-gradient(70% 90% at 100% 0%, color-mix(in srgb, var(--info) 22%, transparent), transparent 70%), var(--brand-navy)';
const team = [
    { initials: 'MW', name: 'Martijn de Wit', role: 'Owner' },
    { initials: 'SV', name: 'Sanne Vos', role: 'Admin' },
    { initials: 'LB', name: 'Lisa Bakker', role: 'Member' },
    { initials: 'JK', name: 'Joost Kramer', role: 'Member' },
];
const tag = 'absolute rounded-md bg-surface px-2 py-1 text-[11px] font-semibold text-ink shadow-lg';
</script>

<template>
    <div class="relative flex h-[290px] w-full items-center justify-center overflow-hidden rounded-xl" :style="{ background: ground }" aria-hidden="true">
        <!-- A display for every room -->
        <div v-if="feature === 'displays'" class="relative h-full w-full">
            <div class="absolute top-[10%] left-[8%]"><RealDisplay :size="250" tone="reserved" room="Boardroom" title="Q4 planning" detail="Until 10:30, 42 min left" :buttons="['End now', '+15']" next="Design sync" /></div>
            <div class="absolute top-[24%] left-[36%] z-10"><RealDisplay :size="270" room="Focus 1" title="All yours!" detail="Free until 11:00" /></div>
            <div class="absolute top-[10%] right-[8%]"><RealDisplay :size="250" room="Atlas" title="All yours!" detail="Free for the rest of the day" next="Nothing else today" next-time="" /></div>
        </div>

        <!-- A display in your own look -->
        <div v-else-if="feature === 'configure'" class="relative">
            <RealDisplay :size="360" :photo="photo" logo room="Summit" subline="Floor 3, 8 seats" title="Grab it, it's free" detail="Free until 11:00" :buttons="['Book 30 min', 'Book 60 min']" next="Team sync" />
            <span :class="tag" class="-top-3 left-6">Your logo</span>
            <span :class="tag" class="top-1/3 -right-6">Your background</span>
            <span :class="tag" class="-bottom-3 left-1/3">Your texts and buttons</span>
        </div>

        <!-- Every room on one screen -->
        <RealBoard v-else-if="feature === 'boards'" :size="440" />

        <!-- One profile, many displays -->
        <div v-else-if="feature === 'profiles'" class="flex items-center gap-6">
            <div class="flex w-48 flex-col gap-2 rounded-lg bg-surface p-3.5 text-[12px] text-ink shadow-xl">
                <span class="font-semibold">Meeting rooms</span>
                <span class="text-[11px] text-muted">Profile, 12 displays</span>
                <span v-for="s in ['Check-in', 'Book from the display', 'Northpeak branding']" :key="s" class="flex items-center justify-between border-t border-line-soft pt-1.5">
                    {{ s }}<span class="inline-flex h-3.5 w-6 items-center justify-end rounded-full bg-navy p-0.5"><span class="h-2.5 w-2.5 rounded-full bg-white"></span></span>
                </span>
            </div>
            <svg width="40" height="160" viewBox="0 0 40 120" preserveAspectRatio="none" fill="none" stroke="var(--info-border)" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M2 60h12M14 60 38 14M14 60h24M14 60l24 46" /></svg>
            <div class="flex flex-col gap-2">
                <RealDisplay :size="120" :photo="photo" logo room="Summit" title="Grab it, it's free" detail="Free until 11:00" :buttons="[]" />
                <RealDisplay :size="120" :photo="photo" logo tone="reserved" room="Ridge" title="Q4 planning" detail="Until 10:30" :buttons="[]" />
                <RealDisplay :size="120" :photo="photo" logo room="Valley" title="Grab it, it's free" detail="Free all day" :buttons="[]" />
            </div>
        </div>

        <!-- The whole team in one workspace -->
        <div v-else class="flex items-center gap-6">
            <div class="flex w-64 flex-col rounded-lg bg-surface text-ink shadow-xl">
                <span class="border-b border-line-soft px-3.5 py-2.5 text-[12px] font-semibold">Team, Northpeak</span>
                <span v-for="m in team" :key="m.name" class="flex items-center gap-2.5 border-b border-line-soft px-3.5 py-2 text-[12px] last:border-b-0">
                    <span class="flex h-7 w-7 items-center justify-center rounded-full bg-navy-50 text-[10px] font-bold text-navy">{{ m.initials }}</span>
                    <span class="flex-1 font-medium">{{ m.name }}</span>
                    <span class="text-[11px] text-muted">{{ m.role }}</span>
                </span>
            </div>
            <RealDisplay :size="250" room="Boardroom" title="All yours!" detail="Free until 11:00" />
        </div>
    </div>
</template>
