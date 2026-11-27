<script setup>
import { computed, watch } from 'vue';
import { Head } from '@inertiajs/vue3';
import OnboardingLayout from '../../Components/OnboardingLayout.vue';
import { useOnboarding } from '../../Composables/useOnboarding';
import WorkspaceStep from './Steps/WorkspaceStep.vue';
import RoomsStep from './Steps/RoomsStep.vue';
import TabletsStep from './Steps/TabletsStep.vue';
import StartStep from './Steps/StartStep.vue';

const props = defineProps({
    step: { type: String, required: true },
    steps: { type: Array, required: true },
    user: { type: Object, required: true },
    workspace: { type: Object, required: true },
    answers: { type: Object, required: true },
    options: { type: Object, required: true },
    urls: { type: Object, required: true },
});

const onboarding = useOnboarding(props.urls);

const meta = {
    workspace: { name: 'Workspace', sub: 'Organisation or personal' },
    rooms: { name: 'Your organisation', sub: 'Rooms, places, what you need' },
    tablets: { name: 'Your current setup', sub: 'Tablets, what you use now' },
    start: { name: 'Start', sub: 'First display, or look around' },
};
const components = { workspace: WorkspaceStep, rooms: RoomsStep, tablets: TabletsStep, start: StartStep };

const current = computed(() => props.steps.indexOf(props.step));
const rail = computed(() => props.steps.map((key, i) => ({ key, ...meta[key], number: i + 1, done: i < current.value, current: i === current.value })));

const eyebrow = computed(() => `${current.value + 1} of ${props.steps.length}, ${meta[props.step].name}`);

watch(() => props.step, (step) => onboarding.track(step, 'viewed'), { immediate: true });
</script>

<template>
    <Head :title="`Welcome - ${meta[step].name}`" />

    <OnboardingLayout title="Let's get you set up" subtitle="Setting up will only take 1 minute and help us provide the best experience."
                      :items="rail" :user="user" :logout-url="urls.logout">
        <component :is="components[step]" v-bind="$props" :eyebrow="eyebrow" :onboarding="onboarding" />
    </OnboardingLayout>
</template>
