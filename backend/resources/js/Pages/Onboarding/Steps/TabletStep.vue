<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import StepFooter from '../../../Components/StepFooter.vue';

defineOptions({ inheritAttrs: false });

const props = defineProps({
    display: Object, device: Object, connectCode: String, urls: Object, next: String, previous: String, onboarding: Object,
});
defineEmits(['skip-setup']);

const paired = ref(props.device);
const copied = ref(false);
let timer = null;

const code = computed(() => (props.connectCode ? `${props.connectCode.slice(0, 3)} ${props.connectCode.slice(3)}` : null));

async function poll() {
    try {
        const response = await fetch(props.urls.deviceStatus, { headers: { Accept: 'application/json' } });
        const data = await response.json();
        if (data.paired) {
            paired.value = { name: data.name };
            stop();
        }
    } catch {
        // A missed poll is retried on the next tick.
    }
}

function stop() {
    if (timer) clearInterval(timer);
    timer = null;
}

async function copy() {
    await navigator.clipboard?.writeText(props.connectCode);
    copied.value = true;
    setTimeout(() => (copied.value = false), 2000);
}

onMounted(() => {
    if (!paired.value && props.connectCode) timer = setInterval(poll, 3000);
});
onUnmounted(stop);
</script>

<template>
    <div class="flex flex-col gap-2">
        <h1 class="text-[28px] leading-tight font-bold tracking-tight">
            {{ display ? `Pair the tablet outside ${display.name}` : 'Pair a tablet' }}
        </h1>
        <p class="text-muted">Install Spacepad from the App Store or Play Store, open it and type this code. This page updates the moment the tablet connects.</p>
    </div>

    <div v-if="!display" class="rounded-xl border border-line bg-white p-5 text-[13px] text-muted">
        Create your first display before pairing a tablet.
    </div>

    <div v-else class="grid gap-4 sm:grid-cols-2">
        <div class="flex flex-col gap-3 rounded-xl border border-line bg-white p-5">
            <span class="text-xs font-semibold tracking-[0.06em] text-muted uppercase">Connect code</span>
            <span class="font-mono text-4xl font-bold tracking-[0.12em] text-navy tabular-nums">{{ code }}</span>
            <span class="text-xs text-muted">Valid for 30 minutes · one tablet</span>
            <button type="button" class="self-start rounded-lg border border-slate-300 px-3 py-1.5 text-[13px] font-semibold text-navy hover:border-navy" @click="copy">
                {{ copied ? 'Copied' : 'Copy code' }}
            </button>
        </div>

        <div class="flex flex-col justify-center gap-2 rounded-xl border p-5"
             :class="paired ? 'border-available-bg bg-available-bg/40' : 'border-dashed border-slate-300 bg-white'">
            <span class="flex items-center gap-2.5 font-semibold">
                <span class="h-2.5 w-2.5 rounded-full" :class="paired ? 'bg-available' : 'animate-pulse bg-checkin'"></span>
                {{ paired ? `${paired.name || 'Tablet'} connected` : 'Waiting for a tablet' }}
            </span>
            <span class="text-[13px] text-muted">{{ paired ? `Showing ${display.name}` : 'No tablet has entered the code yet.' }}</span>
        </div>
    </div>

    <StepFooter :previous="previous" :show-skip="false"
                :continue-label="paired ? 'Finish setup' : 'Pair later'"
                @back="onboarding.goTo(previous)" @skip-setup="$emit('skip-setup')"
                @continue="paired ? onboarding.completeAndGo('tablet', 'ready') : onboarding.skipAndGo('tablet', 'ready')" />
</template>
