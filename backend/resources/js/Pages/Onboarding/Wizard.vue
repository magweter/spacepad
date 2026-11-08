<script setup>
import { computed, watch } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import Logo from '../../Components/Logo.vue';
import FlashMessage from '../../Components/FlashMessage.vue';
import { useOnboarding } from '../../Composables/useOnboarding';
import WorkspaceStep from './Steps/WorkspaceStep.vue';
import AboutStep from './Steps/AboutStep.vue';
import ProductsStep from './Steps/ProductsStep.vue';
import CalendarStep from './Steps/CalendarStep.vue';
import RoomStep from './Steps/RoomStep.vue';
import TabletStep from './Steps/TabletStep.vue';
import ReadyStep from './Steps/ReadyStep.vue';

const props = defineProps({
    step: { type: String, required: true },
    steps: { type: Array, required: true },
    user: { type: Object, required: true },
    workspace: { type: Object, required: true },
    answers: { type: Object, required: true },
    options: { type: Object, required: true },
    accounts: { type: Array, required: true },
    display: { type: Object, default: null },
    canCreateDisplay: { type: Boolean, default: true },
    device: { type: Object, default: null },
    connectCode: { type: String, default: null },
    urls: { type: Object, required: true },
});

const page = usePage();
const onboarding = useOnboarding(props.urls);

const meta = {
    workspace: { name: 'Workspace', sub: 'Organisation or personal, name', tip: ['One question', 'Business or personal decides the plan and the copy. You can change it later.'] },
    about: { name: 'About you', sub: 'Role, current setup, size', tip: ['Why we ask', 'It tells us which setup to show you, and helps us when you switch from another product.'] },
    products: { name: 'Products', sub: 'Display, Board, Console', tip: ['No commitment', 'Picking a product only tells us what you are after. You pay per display, and the first one is free.'] },
    calendar: { name: 'Calendar', sub: 'Connect where rooms live', tip: ['Read-only is enough', 'Displays only need to read the room calendars. Booking from the tablet can be switched on later.'] },
    room: { name: 'Room', sub: 'Pick the first room', tip: ['Just one for now', 'Add the other rooms once the first display is on the wall.'] },
    tablet: { name: 'Tablet', sub: 'One code on the device', tip: ['Any tablet', 'iPad from iOS 15, Android from 8. Install Spacepad from the App Store or Play Store.'] },
    ready: { name: 'Ready', sub: 'What to do next', tip: ['Four minutes', 'Most teams see a display on the wall within four minutes of this screen.'] },
};

const components = { workspace: WorkspaceStep, about: AboutStep, products: ProductsStep, calendar: CalendarStep, room: RoomStep, tablet: TabletStep, ready: ReadyStep };

const current = computed(() => props.steps.indexOf(props.step));
const rail = computed(() => props.steps.map((key, i) => ({
    key,
    ...meta[key],
    number: i + 1,
    done: i < current.value,
    current: i === current.value,
})));
const tip = computed(() => meta[props.step].tip);

function next() {
    return props.steps[current.value + 1] ?? 'ready';
}

function previous() {
    return props.steps[current.value - 1] ?? null;
}

function skipSetup() {
    router.post(props.urls.skip, { step: props.step });
}

function logout() {
    router.post(props.urls.logout);
}

watch(() => props.step, (step) => onboarding.track(step, 'viewed'), { immediate: true });
</script>

<template>
    <Head :title="`Set up · ${meta[step].name}`" />

    <div class="flex min-h-screen flex-col bg-white text-sm leading-normal text-ink lg:flex-row">
        <!-- Rail -->
        <aside class="flex flex-none flex-col gap-8 bg-navy px-6 py-8 text-white lg:w-[360px] lg:px-10 lg:py-10">
            <Logo dark />
            <div class="hidden flex-col gap-2 lg:flex">
                <h2 class="text-[22px] leading-tight font-bold tracking-tight">Your first room display in about four minutes</h2>
                <p class="text-on-navy-2">{{ steps.length }} short steps. Skip any of them; nothing here is final.</p>
            </div>

            <ol class="flex gap-2 overflow-x-auto lg:flex-col lg:gap-1">
                <li v-for="item in rail" :key="item.key" class="flex flex-none items-center gap-3 rounded-lg lg:px-2 lg:py-2"
                    :class="item.current ? 'lg:bg-navy-800' : ''">
                    <span class="flex h-7 w-7 flex-none items-center justify-center rounded-full border text-xs font-bold"
                          :class="item.done ? 'border-amber bg-amber text-navy' : item.current ? 'border-white bg-white text-navy' : 'border-navy-600 text-on-navy-muted'">
                        {{ item.done ? '✓' : item.number }}
                    </span>
                    <span class="hidden flex-col lg:flex">
                        <span class="font-semibold" :class="item.current || item.done ? 'text-white' : 'text-on-navy-muted'">{{ item.name }}</span>
                        <span class="text-xs text-on-navy-muted">{{ item.sub }}</span>
                    </span>
                </li>
            </ol>

            <div class="mt-auto hidden flex-col gap-1.5 rounded-xl bg-navy-800 p-4 lg:flex">
                <span class="text-xs font-semibold tracking-[0.06em] text-amber uppercase">{{ tip[0] }}</span>
                <span class="text-[13px] text-on-navy-2">{{ tip[1] }}</span>
            </div>
        </aside>

        <!-- Step -->
        <main class="flex min-w-0 flex-1 flex-col bg-ground px-6 py-8 lg:px-16 lg:py-12">
            <div class="flex items-center justify-end gap-3 text-xs text-muted">
                <span>Signed in as {{ user.email }}</span>
                <button type="button" class="font-semibold text-navy hover:underline" @click="logout">Log out</button>
            </div>

            <div class="mx-auto flex w-full max-w-[680px] flex-1 flex-col gap-6 pt-8 lg:pt-12">
                <span class="text-xs font-semibold tracking-[0.06em] text-muted uppercase">
                    Step {{ current + 1 }} of {{ steps.length }} · {{ meta[step].name }}
                </span>

                <FlashMessage />

                <component :is="components[step]" v-bind="$props" :next="next()" :previous="previous()"
                           :onboarding="onboarding" @skip-setup="skipSetup" />
            </div>
        </main>
    </div>
</template>
