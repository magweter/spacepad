<script setup>
import { router } from '@inertiajs/vue3';
import StepFooter from '../../../Components/StepFooter.vue';

defineOptions({ inheritAttrs: false });

const props = defineProps({
    display: Object, device: Object, accounts: Array, urls: Object, previous: String, onboarding: Object,
});
defineEmits(['skip-setup']);

function finish() {
    props.onboarding.track('ready', 'completed');
    router.post(props.urls.complete);
}
</script>

<template>
    <div class="flex flex-col gap-2">
        <h1 class="text-[28px] leading-tight font-bold tracking-tight">
            {{ display && device ? `${display.name} is on the wall.` : 'Your workspace is ready.' }}
        </h1>
        <p class="text-muted">
            <template v-if="display && device">The display reads the room calendar in real time.</template>
            <template v-else-if="display">Pair a tablet whenever you like; the connect code is on your dashboard.</template>
            <template v-else>Connect a calendar and add a display from your dashboard whenever you are ready.</template>
        </p>
    </div>

    <ul class="flex flex-col gap-2 rounded-xl border border-line bg-white p-5 text-[13px]">
        <li class="flex items-center gap-2.5"><span class="h-2 w-2 rounded-full" :class="accounts.length ? 'bg-available' : 'bg-slate-300'"></span>{{ accounts.length ? `${accounts[0].providerLabel} connected` : 'No calendar connected yet' }}</li>
        <li class="flex items-center gap-2.5"><span class="h-2 w-2 rounded-full" :class="display ? 'bg-available' : 'bg-slate-300'"></span>{{ display ? `Display for ${display.name}` : 'No display yet' }}</li>
        <li class="flex items-center gap-2.5"><span class="h-2 w-2 rounded-full" :class="device ? 'bg-available' : 'bg-slate-300'"></span>{{ device ? `${device.name || 'Tablet'} paired` : 'No tablet paired yet' }}</li>
    </ul>

    <StepFooter :previous="previous" :show-skip="false" continue-label="Finish"
                @back="onboarding.goTo(previous)" @skip-setup="$emit('skip-setup')" @continue="finish" />
</template>
