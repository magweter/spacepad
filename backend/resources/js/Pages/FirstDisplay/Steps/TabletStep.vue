<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import StepHeading from '../../../Components/StepHeading.vue';
import Button from '../../../DesignSystem/Button.vue';
import Card from '../../../DesignSystem/Card.vue';
import StatusDot from '../../../DesignSystem/StatusDot.vue';

defineOptions({ inheritAttrs: false });

const props = defineProps({ display: Object, device: Object, connectCode: String, urls: Object, eyebrow: String, flow: Object });

const paired = ref(props.device);
const copied = ref(false);
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
    await navigator.clipboard?.writeText(code.value);
    copied.value = true;
    setTimeout(() => (copied.value = false), 2000);
}

onMounted(() => {
    if (!paired.value && props.connectCode) timer = setInterval(poll, 3000);
});
onUnmounted(stop);
</script>

<template>
    <StepHeading :eyebrow="eyebrow" :title="display ? `Pair the tablet outside ${display.name}` : 'Pair a tablet'">
        Install Spacepad from the App Store or Play Store, open it and type this code. This page updates the moment the tablet connects.
    </StepHeading>

    <Card v-if="!display" padding="md"><span class="text-[13px] text-muted">Create the display first, then pair a tablet.</span></Card>

    <div v-else class="grid gap-4 sm:grid-cols-[1.1fr_1fr]">
        <!-- What the tablet shows while it waits for the code -->
        <div class="flex flex-col gap-2">
            <div class="rounded-[17.6px] bg-console-ground p-3 shadow-[var(--shadow-device)]">
                <div class="flex aspect-[16/10] flex-col items-center justify-center gap-3 rounded-md bg-[#0B1221] p-4 text-white">
                    <span class="font-display text-lg font-bold">Connect this display</span>
                    <span class="text-xs text-slate-400">Enter the code from the portal</span>
                    <span class="flex gap-1.5 font-mono text-base font-semibold">
                        <span v-for="(digit, i) in code.split('')" :key="i" class="flex h-9 w-7 items-center justify-center rounded-md border border-[var(--glass-border)] bg-white/10">{{ paired ? digit : (i < 2 ? digit : '') }}</span>
                    </span>
                </div>
            </div>
            <span class="text-xs text-muted">What the tablet shows right now</span>
        </div>

        <div class="flex flex-col gap-3">
            <Card>
                <span class="eyebrow text-muted uppercase">Connect code</span>
                <span class="font-mono text-[32px] leading-none font-semibold tracking-[0.12em] text-navy tabular-nums">{{ pretty }}</span>
                <span class="text-xs text-muted">Valid for 30 minutes, one tablet</span>
                <Button variant="secondary" size="sm" class="self-start" @click="copy">{{ copied ? 'Copied' : 'Copy code' }}</Button>
            </Card>
            <Card :rule="paired ? 'available' : 'checkin'">
                <span class="flex items-center gap-2 font-semibold">
                    <StatusDot :status="paired ? 'available' : 'checkin'" />
                    {{ paired ? `${paired.name || 'Tablet'} connected` : 'Waiting for a tablet' }}
                </span>
                <span class="text-[13px] text-muted">{{ paired ? `Showing ${display.name}, online` : 'No tablet has entered the code yet.' }}</span>
            </Card>
        </div>
    </div>

    <div class="flex items-center justify-between">
        <button type="button" class="text-[13px] font-semibold text-muted hover:text-navy" @click="flow.goTo('room')">← Back</button>
        <Button v-if="paired" size="lg" @click="flow.completeAndGo('tablet', 'done')">Finish</Button>
        <Button v-else variant="secondary" size="lg" @click="flow.skipAndGo('tablet', 'done')">Pair later</Button>
    </div>
</template>
