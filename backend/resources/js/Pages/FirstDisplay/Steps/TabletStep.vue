<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import StepHeading from '../../../Components/StepHeading.vue';
import Card from '../../../DesignSystem/Card.vue';
import StatusDot from '../../../DesignSystem/StatusDot.vue';
import NextSteps from '../Partials/NextSteps.vue';

defineOptions({ inheritAttrs: false });

const props = defineProps({ display: Object, device: Object, connectCode: String, urls: Object, eyebrow: String, flow: Object });

const paired = ref(props.device);
let timer = null;

const code = computed(() => props.connectCode ?? '');
const pretty = computed(() => (code.value ? `${code.value.slice(0, 3)} ${code.value.slice(3)}` : ''));

async function poll() {
    try {
        const response = await fetch(props.urls.deviceStatus, { headers: { Accept: 'application/json' } });
        const data = await response.json();
        if (data.paired) {
            paired.value = { name: data.name };
            stop();
            // Pairing completes the step; there is no separate "done" screen any more.
            props.flow.track('tablet', 'completed');
        }
    } catch {
        // A missed poll is retried on the next tick.
    }
}

function stop() {
    if (timer) clearInterval(timer);
    timer = null;
}


onMounted(() => {
    if (!paired.value && props.connectCode) timer = setInterval(poll, 3000);
});

// Leaving through one of the next steps without a tablet: the step was skipped, to pair later.
function leave() {
    if (!paired.value) props.flow.track('tablet', 'skipped');
}
onUnmounted(stop);
</script>

<template>
    <StepHeading :eyebrow="eyebrow" :title="paired && display ? `${display.name} is connected.` : display ? `Pair the tablet outside ${display.name}` : 'Pair a tablet'">
        <template v-if="paired">The tablet now shows whether {{ display.name }} is free or in use, straight from its calendar.</template>
        <template v-else>Install Spacepad from the App Store or Play Store, open it and type this code. This page updates the moment the tablet connects.</template>
    </StepHeading>

    <Card v-if="!display" padding="md"><span class="text-[13px] text-muted">Create the display first, then pair a tablet.</span></Card>

    <!-- Paired: only the confirmation; the tablet mock-up and the code are for pairing. -->
    <Card v-else-if="paired" rule="available">
        <span class="flex items-center gap-2 font-semibold"><StatusDot status="available" />{{ paired.name || 'Tablet' }} connected</span>
        <span class="text-[13px] text-muted">Online now and paired to this display.</span>
    </Card>
    <div v-else class="grid gap-4 sm:grid-cols-[1.1fr_1fr]">
        <!-- What the tablet shows while it waits for the code; as tall as the two cards beside it. -->
        <div class="flex rounded-[17.6px] bg-console-ground p-3 shadow-[var(--shadow-device)]">
                <div class="flex min-h-[200px] flex-1 flex-col items-center justify-center gap-3 rounded-md bg-[#0B1221] p-4 text-white">
                    <span class="font-display text-lg font-bold">Connect this display</span>
                    <span class="text-xs text-slate-400">Enter the code from the portal</span>
                    <span class="flex gap-1.5 font-mono text-base font-semibold">
                        <span v-for="(digit, i) in code.split('')" :key="i" class="flex h-9 w-7 items-center justify-center rounded-md border border-[var(--glass-border)] bg-white/10">{{ paired ? digit : (i < 2 ? digit : '') }}</span>
                    </span>
                </div>
        </div>

        <div class="flex flex-col gap-3">
            <Card class="flex-1 justify-center">
                <span class="eyebrow text-muted uppercase">Connect code</span>
                <span class="font-mono text-[32px] leading-none font-semibold tracking-[0.12em] text-navy tabular-nums">{{ pretty }}</span>
                <span class="text-xs text-muted">Valid for 30 minutes on one tablet</span>
            </Card>
            <Card :rule="paired ? 'available' : 'checkin'" class="flex-1 justify-center">
                <span class="flex items-center gap-2 font-semibold">
                    <StatusDot :status="paired ? 'available' : 'checkin'" />
                    {{ paired ? `${paired.name || 'Tablet'} connected` : 'Waiting for a tablet' }}
                </span>
                <span class="text-[13px] text-muted">{{ paired ? 'Online now and paired to this display.' : 'No tablet has entered the code yet.' }}</span>
            </Card>
        </div>
    </div>

    <NextSteps v-if="display" :display="display" :paired="!!paired" :urls="urls" @leave="leave" />
</template>
