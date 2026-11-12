<script setup>
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import Logo from '../../Components/Logo.vue';
import DevicePreview from '../../Components/DevicePreview.vue';
import Button from '../../DesignSystem/Button.vue';
import Chip from '../../DesignSystem/Chip.vue';

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
    <Head :title="live ? `${display.name} is live` : 'Your display'" />

    <div class="flex min-h-screen flex-col bg-ground text-sm leading-normal text-ink">
        <header class="flex flex-wrap items-center gap-4 px-6 py-5 md:px-12">
            <Logo />
            <div class="flex-1"></div>
            <Chip v-if="live" tone="available">Setup complete</Chip>
        </header>

        <main class="flex flex-1 flex-wrap items-center justify-center gap-12 px-6 pt-6 pb-16 md:px-12">
            <div class="flex max-w-[680px] min-w-0 flex-[1_1_520px] flex-col gap-3.5">
                <DevicePreview :room="display?.name || 'Your room'" :time="time" meta="" :scale="1.15" />
                <div class="flex flex-wrap items-center justify-between gap-2 text-[13px] text-muted">
                    <span class="inline-flex items-center gap-2">
                        <span class="sp-dot sp-dot-sm" :class="live ? 'sp-dot-available' : 'sp-dot-offline'"></span>
                        <template v-if="live">{{ device.name || 'Tablet' }}, online<template v-if="provider">, synced with {{ provider }}</template></template>
                        <template v-else>No tablet paired yet</template>
                    </span>
                    <a v-if="urls.configure" :href="urls.configure" class="font-semibold text-navy">Customise this display →</a>
                </div>
            </div>

            <div class="flex max-w-[520px] min-w-0 flex-[1_1_400px] flex-col gap-7">
                <div class="flex flex-col gap-2.5">
                    <span class="text-xs font-semibold tracking-[0.08em] text-checkin-text uppercase">{{ live ? 'Your first display is live' : 'Your display is ready' }}</span>
                    <h1 class="font-display text-4xl leading-[1.1] font-bold tracking-tight">
                        {{ live ? `${display.name} is on the wall.` : `${display?.name || 'The display'} is ready for a tablet.` }}
                    </h1>
                    <p class="text-[15px] text-muted">It reads the room calendar in real time and lets people book from the tablet. Here's what most teams do next.</p>
                </div>

                <div class="flex flex-col gap-2.5">
                    <a :href="urls.addDisplay" class="sp-card sp-card-pad-sm sp-card-interactive">
                        <span class="flex items-center justify-between gap-3">
                            <span class="flex flex-col"><span class="font-semibold">Add the other rooms</span><span class="text-xs text-muted">Same steps, with the calendar already connected.</span></span>
                            <Chip v-if="!hasPro" tone="info">Pro</Chip>
                        </span>
                    </a>
                    <a :href="urls.team" class="sp-card sp-card-pad-sm sp-card-interactive">
                        <span class="flex items-center justify-between gap-3">
                            <span class="flex flex-col"><span class="font-semibold">Invite facilities or IT</span><span class="text-xs text-muted">No charge per member. Give reception a seat so they see room status too.</span></span>
                            <Chip v-if="!hasPro" tone="info">Pro</Chip>
                        </span>
                    </a>
                    <a :href="urls.boards" class="sp-card sp-card-pad-sm sp-card-interactive">
                        <span class="flex items-center justify-between gap-3">
                            <span class="flex flex-col"><span class="font-semibold">Put an overview on the lobby TV</span><span class="text-xs text-muted">A board shows every room at once. Share it with a public link, no login.</span></span>
                            <Chip v-if="!hasPro" tone="info">Pro</Chip>
                        </span>
                    </a>
                </div>

                <div>
                    <Button size="lg" :href="urls.dashboard" external>Go to my overview →</Button>
                </div>
            </div>
        </main>
    </div>
</template>
