<script setup>
import Button from '../../../DesignSystem/Button.vue';
import Icon from '../../../Components/Icon.vue';
import { onMounted, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import confetti from 'canvas-confetti';
import StepHeading from '../../../Components/StepHeading.vue';

defineOptions({ inheritAttrs: false });

const props = defineProps({ urls: Object, eyebrow: String, onboarding: Object });
const processing = ref(false);

// A small celebration on arrival, in the brand colours. Skipped for anyone who asked their
// system for less motion.
onMounted(() => {
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    const colors = ['#FDA312', '#14213D', '#16A34A', '#FFFFFF'];
    confetti({ particleCount: 90, spread: 70, startVelocity: 38, origin: { x: 0.62, y: 0.25 }, colors, disableForReducedMotion: true });
    setTimeout(() => confetti({ particleCount: 50, spread: 100, origin: { x: 0.62, y: 0.3 }, colors, disableForReducedMotion: true }), 250);
});

function start(choice) {
    processing.value = true;
    router.post(props.urls.start, { choice }, { onFinish: () => (processing.value = false) });
}
</script>

<template>
    <StepHeading :eyebrow="eyebrow" title="Great! You're ready to go.">
        <template #before-title>
            <span class="mb-1 flex h-12 w-12 items-center justify-center rounded-full bg-available-bg text-available-text">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5" /></svg>
            </span>
        </template>
        Your workspace is set up. How do you want to start? You can always do the other one later from the portal.
    </StepHeading>

    <div class="flex flex-col gap-3">
        <button type="button" :disabled="processing" @click="start('first_display')"
                class="flex items-center gap-4 rounded-[14px] border-2 border-navy bg-navy px-[22px] py-5 text-left text-white transition-colors hover:bg-navy-800 disabled:opacity-60">
            <span class="flex h-12 w-12 flex-none items-center justify-center rounded-lg bg-amber text-navy">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="3" width="20" height="14" rx="2" /><path d="M8 21h8M12 17v4" /></svg>
            </span>
            <span class="flex flex-1 flex-col gap-[3px]">
                <span class="text-[17px] font-bold">Set up my first display</span>
                <span class="text-[13px] text-on-navy-2">Connect your calendar, pick a room, pair a tablet.</span>
            </span>
            <span class="rounded-md bg-amber px-[9px] py-[3px] text-[11px] font-bold whitespace-nowrap text-navy">Recommended</span>
        </button>

        <button type="button" :disabled="processing" @click="start('look_around')"
                class="flex items-center gap-4 rounded-[14px] border-2 border-line bg-surface px-[22px] py-5 text-left text-ink transition-colors hover:border-navy disabled:opacity-60">
            <span class="flex h-12 w-12 flex-none items-center justify-center rounded-lg bg-line-soft text-ink-2">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8" /><path d="m21 21-4.3-4.3" /></svg>
            </span>
            <span class="flex flex-1 flex-col gap-[3px]">
                <span class="text-[17px] font-bold">Just look around</span>
                <span class="text-[13px] text-muted">Go to the portal first. Setting up your first display stays one click away on the overview.</span>
            </span>
        </button>
    </div>

    <div>
        <Button variant="ghost" @click="onboarding.goTo('tablets')"><template #icon><Icon name="arrowLeft" :size="16" /></template>Back</Button>
    </div>
</template>
