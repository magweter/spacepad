<script setup>
import { computed, watch } from 'vue';
import { Head } from '@inertiajs/vue3';
import PortalShell from '../../Components/PortalShell.vue';
import { useOnboarding } from '../../Composables/useOnboarding';
import CalendarStep from './Steps/CalendarStep.vue';
import RoomStep from './Steps/RoomStep.vue';
import TabletStep from './Steps/TabletStep.vue';

const props = defineProps({
    step: { type: String, required: true },
    steps: { type: Array, required: true },
    shell: { type: Object, required: true },
    accounts: { type: Array, required: true },
    display: { type: Object, default: null },
    device: { type: Object, default: null },
    connectCode: { type: String, default: null },
    canCreateDisplay: { type: Boolean, default: true },
    urls: { type: Object, required: true },
});

const flow = useOnboarding(props.urls);

const meta = {
    calendar: { name: 'Calendar', tip: null },
    room: { name: 'Room', tip: null },
    tablet: { name: 'Tablet', tip: null },
};
const components = { calendar: CalendarStep, room: RoomStep, tablet: TabletStep };

const current = computed(() => props.steps.indexOf(props.step));
const stepper = computed(() => props.steps.map((key, i) => ({ key, name: meta[key].name, number: i + 1, done: i < current.value, current: i === current.value, last: i === props.steps.length - 1 })));
const eyebrow = computed(() => `${current.value + 1} of ${props.steps.length}, ${meta[props.step].name}`);

watch(() => props.step, (step) => flow.track(step, 'viewed'), { immediate: true });
</script>

<template>
    <Head :title="`Set up a display - ${meta[step].name}`" />

    <PortalShell :shell="shell">
        <header class="flex flex-wrap items-center justify-between gap-x-8 gap-y-4 border-b border-line bg-surface px-8 py-3.5">
            <div class="flex flex-col gap-px">
                <span class="text-base font-bold tracking-tight">Set up your first display</span>
                <span class="text-xs text-muted">Your progress is saved automatically.</span>
            </div>
            <ol aria-label="Steps" class="flex flex-wrap items-center">
                <li v-for="item in stepper" :key="item.key" class="flex items-center">
                    <span class="inline-flex items-center gap-2" :aria-current="item.current ? 'step' : null">
                        <span class="flex h-6 w-6 items-center justify-center rounded-full border-2 text-xs font-bold"
                              :class="item.done ? 'border-navy bg-navy text-white' : item.current ? 'border-navy bg-surface text-navy' : 'border-offline-border bg-surface text-offline'">
                            {{ item.done ? '✓' : item.number }}
                        </span>
                        <span class="text-[13px] font-semibold whitespace-nowrap" :class="item.current || item.done ? 'text-ink' : 'text-muted'">{{ item.name }}</span>
                    </span>
                    <span v-if="!item.last" class="mx-3 h-0.5 w-8 rounded-sm" :class="item.done ? 'bg-navy' : 'bg-line'"></span>
                </li>
            </ol>
        </header>

        <div class="mx-auto flex w-full max-w-[760px] flex-col gap-6 px-6 py-8 md:px-8 md:py-12">
            <component :is="components[step]" v-bind="$props" :eyebrow="eyebrow" :flow="flow" />
            <p v-if="meta[step].tip" class="text-[13px] text-muted"><span class="font-semibold text-ink-2">{{ meta[step].tip[0] }}.</span> {{ meta[step].tip[1] }}</p>
        </div>
    </PortalShell>
</template>
