<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import StepHeading from '../../../Components/StepHeading.vue';

defineOptions({ inheritAttrs: false });

const props = defineProps({ urls: Object, eyebrow: String, onboarding: Object });
const processing = ref(false);

function start(choice) {
    processing.value = true;
    router.post(props.urls.start, { choice }, { onFinish: () => (processing.value = false) });
}
</script>

<template>
    <StepHeading :eyebrow="eyebrow" title="How do you want to start?">
        Your workspace is ready. Neither is final; you can do the other any time from the portal.
    </StepHeading>

    <div class="flex flex-col gap-3">
        <button type="button" :disabled="processing" @click="start('first_display')"
                class="flex items-center gap-4 rounded-[14px] border-2 border-navy bg-navy px-[22px] py-5 text-left text-white transition-colors hover:bg-navy-800 disabled:opacity-60">
            <span class="flex h-12 w-12 flex-none items-center justify-center rounded-lg bg-amber text-navy">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="3" width="20" height="14" rx="2" /><path d="M8 21h8M12 17v4" /></svg>
            </span>
            <span class="flex flex-1 flex-col gap-[3px]">
                <span class="text-[17px] font-bold">Set up my first display</span>
                <span class="text-[13px] text-on-navy-2">Connect your calendar, pick a room, pair a tablet. Four steps, about four minutes.</span>
            </span>
            <span class="rounded-full bg-amber px-[9px] py-[3px] text-[11px] font-bold whitespace-nowrap text-navy">Recommended</span>
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
        <button type="button" class="text-[13px] font-semibold text-muted hover:text-navy" @click="onboarding.goTo('tablets')">← Back</button>
    </div>
</template>
