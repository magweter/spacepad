<script setup>
import { computed, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import Logo from '../../Components/Logo.vue';
import FlashMessage from '../../Components/FlashMessage.vue';
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
    rooms: { name: 'Your rooms', sub: 'Today, how many, where' },
    tablets: { name: 'Tablets and features', sub: 'What they run on, what matters' },
    start: { name: 'Start', sub: 'First display, or look around' },
};
const components = { workspace: WorkspaceStep, rooms: RoomsStep, tablets: TabletsStep, start: StartStep };

const current = computed(() => props.steps.indexOf(props.step));
const rail = computed(() => props.steps.map((key, i) => ({ key, ...meta[key], number: i + 1, done: i < current.value, current: i === current.value })));
const initials = computed(() => (props.user.name || props.user.email || '?').split(/[\s.@]+/).filter(Boolean).slice(0, 2).map((p) => p[0]).join('').toUpperCase());

function logout() {
    router.post(props.urls.logout);
}

const eyebrow = computed(() => `${current.value + 1} of ${props.steps.length}, ${meta[props.step].name}`);

watch(() => props.step, (step) => onboarding.track(step, 'viewed'), { immediate: true });
</script>

<template>
    <Head :title="`Welcome · ${meta[step].name}`" />

    <div class="flex min-h-screen flex-wrap bg-ground text-sm leading-normal text-ink">
        <!-- Rail -->
        <aside class="flex flex-[0_0_100%] flex-col gap-8 bg-navy px-6 py-7 text-on-navy-2 md:flex-[0_0_340px] md:p-9">
            <Logo dark />
            <div class="flex flex-col gap-1.5">
                <h1 class="text-2xl leading-[1.2] font-bold tracking-tight text-white">Four steps to get you set up</h1>
                <p class="text-[13px] text-on-navy-muted">Setting up will only take 1 minute and help us provide the best experience.</p>
            </div>
            <ol class="flex flex-col gap-1">
                <li v-for="item in rail" :key="item.key" class="flex items-start gap-3 py-2.5">
                    <span class="flex h-[26px] w-[26px] flex-none items-center justify-center rounded-full border-2 text-xs font-bold"
                          :class="item.done ? 'border-amber bg-amber text-navy' : item.current ? 'border-white bg-white text-navy' : 'border-navy-600 text-on-navy-muted'">
                        {{ item.done ? '✓' : item.number }}
                    </span>
                    <span class="flex flex-col gap-px">
                        <span class="font-semibold" :class="item.current || item.done ? 'text-white' : 'text-on-navy-muted'">{{ item.name }}</span>
                        <span class="text-xs text-on-navy-muted">{{ item.sub }}</span>
                    </span>
                </li>
            </ol>
            <div class="flex-1"></div>
            <div class="flex items-center gap-2.5 border-t border-navy-700 pt-4">
                <span class="flex h-8 w-8 flex-none items-center justify-center rounded-full bg-on-navy-muted text-[13px] font-semibold text-navy">{{ initials }}</span>
                <span class="flex min-w-0 flex-1 flex-col">
                    <span class="truncate text-[13px] font-medium text-white">{{ user.name }}</span>
                    <span class="truncate text-xs text-on-navy-muted">{{ user.email }}</span>
                </span>
                <button type="button" class="rounded-md px-2 py-1 text-xs font-semibold text-on-navy-2 hover:bg-navy-800 hover:text-white" @click="logout">Log out</button>
            </div>
        </aside>

        <!-- Step -->
        <main class="flex min-w-0 flex-[999_1_560px] flex-col items-center px-6 py-10 md:px-10 md:py-14">
            <div class="flex w-full max-w-[720px] flex-col gap-6">
                <FlashMessage />
                <component :is="components[step]" v-bind="$props" :eyebrow="eyebrow" :onboarding="onboarding" />
            </div>
        </main>
    </div>
</template>
