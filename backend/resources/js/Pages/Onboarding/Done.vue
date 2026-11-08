<script setup>
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import Logo from '../../Components/Logo.vue';
import TabletPreview from '../../Components/TabletPreview.vue';

const props = defineProps({
    display: { type: Object, default: null },
    device: { type: Object, default: null },
    provider: { type: String, default: null },
    hasPro: { type: Boolean, default: false },
    urls: { type: Object, required: true },
});

const live = computed(() => props.display && props.device);
const time = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
</script>

<template>
    <Head title="You're set up" />

    <div class="flex min-h-screen flex-col bg-ground text-sm leading-normal text-ink">
        <header class="flex flex-wrap items-center gap-4 px-6 py-5 lg:px-12">
            <Logo />
            <div class="flex-1"></div>
            <span class="inline-flex items-center gap-1.5 rounded-full bg-available-bg px-3 py-1 text-xs font-semibold text-available-text">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5" /></svg>
                Setup complete
            </span>
        </header>

        <main class="flex flex-1 flex-wrap items-center justify-center gap-12 px-6 pt-6 pb-16 lg:px-12">
            <!-- The display -->
            <div class="flex min-w-0 max-w-[560px] flex-[1_1_420px] flex-col gap-3.5">
                <TabletPreview :room="display?.name || 'Your room'" :time="time" large />
                <div class="flex flex-wrap items-center justify-between gap-2 text-[13px] text-muted">
                    <span class="inline-flex items-center gap-2">
                        <span class="h-2 w-2 rounded-full" :class="live ? 'bg-available' : 'bg-slate-300'"></span>
                        <template v-if="live">{{ device.name || 'Tablet' }} · online<template v-if="provider"> · synced with {{ provider }}</template></template>
                        <template v-else>No tablet paired yet</template>
                    </span>
                </div>
            </div>

            <!-- Next steps -->
            <div class="flex min-w-0 max-w-[520px] flex-[1_1_400px] flex-col gap-7">
                <div class="flex flex-col gap-2.5">
                    <span class="text-xs font-semibold tracking-[0.08em] text-checkin-text uppercase">{{ live ? 'Your first display is live' : 'Your workspace is ready' }}</span>
                    <h1 class="text-4xl leading-[1.1] font-bold tracking-tight">
                        {{ live ? `${display.name} is on the wall.` : display ? `${display.name} is ready for a tablet.` : 'Add your first display.' }}
                    </h1>
                    <p class="text-[15px] text-muted">
                        <template v-if="display">It reads the room calendar in real time and lets people book from the tablet. This display is free, forever. Here's what most teams do next.</template>
                        <template v-else>Connect a calendar and pick a room from your overview. The first display is free, forever.</template>
                    </p>
                </div>

                <div class="flex flex-col gap-2.5">
                    <a :href="urls.addDisplay" class="flex items-center gap-3.5 rounded-xl border border-line bg-white px-4 py-3.5 hover:border-navy">
                        <span class="flex h-10 w-10 flex-none items-center justify-center rounded-[10px] bg-amber-100 text-checkin-text">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="3" width="20" height="14" rx="2" /><path d="M8 21h8M12 17v4" /></svg>
                        </span>
                        <span class="flex min-w-0 flex-1 flex-col">
                            <span class="font-semibold">{{ display ? 'Add the other rooms' : 'Create a display' }}</span>
                            <span class="text-xs text-muted">{{ display ? 'Each room gets its own display. More than one display needs Pro.' : 'Pick a room from your calendar.' }}</span>
                        </span>
                        <span v-if="display && !hasPro" class="rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-bold tracking-[0.04em] text-checkin-text">PRO</span>
                    </a>
                    <a :href="urls.team" class="flex items-center gap-3.5 rounded-xl border border-line bg-white px-4 py-3.5 hover:border-navy">
                        <span class="flex h-10 w-10 flex-none items-center justify-center rounded-[10px] bg-info-bg text-info">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" /><circle cx="9" cy="7" r="4" /><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75" /></svg>
                        </span>
                        <span class="flex min-w-0 flex-1 flex-col">
                            <span class="font-semibold">Invite facilities or IT</span>
                            <span class="text-xs text-muted">No charge per team member. Inviting colleagues is part of Pro.</span>
                        </span>
                        <span v-if="!hasPro" class="rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-bold tracking-[0.04em] text-checkin-text">PRO</span>
                    </a>
                    <a :href="urls.boards" class="flex items-center gap-3.5 rounded-xl border border-line bg-white px-4 py-3.5 hover:border-navy">
                        <span class="flex h-10 w-10 flex-none items-center justify-center rounded-[10px] bg-teal-50 text-teal-700">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="7" height="7" rx="1" /><rect x="14" y="3" width="7" height="7" rx="1" /><rect x="14" y="14" width="7" height="7" rx="1" /><rect x="3" y="14" width="7" height="7" rx="1" /></svg>
                        </span>
                        <span class="flex min-w-0 flex-1 flex-col">
                            <span class="font-semibold">Put an overview on the lobby TV</span>
                            <span class="text-xs text-muted">A board shows every room at once. Share it with a public link, no login.</span>
                        </span>
                        <span v-if="!hasPro" class="rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-bold tracking-[0.04em] text-checkin-text">PRO</span>
                    </a>
                </div>

                <a :href="urls.dashboard" class="inline-flex items-center gap-2 self-start rounded-[10px] bg-navy px-5 py-3 text-sm font-semibold text-white hover:bg-navy-800">
                    Go to my overview
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M12 5l7 7-7 7" /></svg>
                </a>
            </div>
        </main>
    </div>
</template>
