<script setup>
import { computed } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import OptionCard from '../../../Components/OptionCard.vue';
import Chip from '../../../Components/Chip.vue';
import StepFooter from '../../../Components/StepFooter.vue';

defineOptions({ inheritAttrs: false });

const props = defineProps({
    answers: Object, options: Object, urls: Object, next: String, previous: String, onboarding: Object,
});
defineEmits(['skip-setup']);

const form = useForm({
    role: props.answers.role,
    previous_solution: props.answers.previous_solution,
    previous_solution_text: props.answers.previous_solution_text ?? '',
    rooms: props.answers.rooms,
    calendar_provider: props.answers.calendar_provider,
});

const switching = computed(() => ['joan', 'teams_panels', 'robin_or_suite'].includes(form.previous_solution));
const toolLabel = computed(() => props.options.previous_solution.find((o) => o.value === form.previous_solution)?.label);

function submit() {
    form.post(props.urls.about, { preserveScroll: true });
}

function skip() {
    router.post(props.urls.about, { skip: true });
}
</script>

<template>
    <div class="flex flex-col gap-2">
        <h1 class="text-[28px] leading-tight font-bold tracking-tight">What's your role, and how do rooms get booked today?</h1>
        <p class="text-muted">This decides which setup path we show you. Every question is optional.</p>
    </div>

    <form class="flex flex-col gap-7" @submit.prevent="submit">
        <fieldset class="flex flex-col gap-2.5">
            <legend class="mb-2.5 text-[13px] font-semibold">I am…</legend>
            <div class="grid gap-2.5 sm:grid-cols-2">
                <OptionCard v-for="option in options.role" :key="option.value" :selected="form.role === option.value" @select="form.role = option.value">
                    <span class="font-semibold">{{ option.label }}</span>
                    <span class="text-[13px] text-muted">{{ option.description }}</span>
                </OptionCard>
            </div>
        </fieldset>

        <fieldset class="flex flex-col gap-2.5">
            <legend class="mb-2.5 text-[13px] font-semibold">Today we use…</legend>
            <div class="flex flex-wrap gap-2">
                <Chip v-for="option in options.previous_solution" :key="option.value" :selected="form.previous_solution === option.value"
                      @select="form.previous_solution = option.value">{{ option.label }}</Chip>
            </div>
            <input v-if="form.previous_solution === 'other'" v-model="form.previous_solution_text" type="text" maxlength="255" placeholder="Name of the product"
                   class="max-w-sm rounded-[10px] border border-slate-300 bg-white px-3.5 py-2.5 text-sm focus:border-navy focus:outline-none">
            <span v-if="form.errors.previous_solution_text" class="text-xs text-red-700">{{ form.errors.previous_solution_text }}</span>
            <p v-if="switching" class="rounded-lg bg-info-bg px-3 py-2 text-[13px] text-info">
                Switching from {{ toolLabel }}? You can run both side by side while you move rooms over.
            </p>
        </fieldset>

        <fieldset class="flex flex-col gap-2.5">
            <legend class="mb-2.5 text-[13px] font-semibold">How many rooms, roughly?</legend>
            <div class="inline-flex self-start rounded-[10px] border border-line bg-white p-1">
                <button v-for="option in options.rooms" :key="option.value" type="button" :aria-pressed="form.rooms === option.value"
                        class="rounded-lg px-4 py-1.5 text-[13px] font-semibold"
                        :class="form.rooms === option.value ? 'bg-navy text-white' : 'text-ink-3 hover:text-navy'"
                        @click="form.rooms = option.value">{{ option.label }}</button>
            </div>
        </fieldset>

        <fieldset class="flex flex-col gap-2.5">
            <legend class="mb-2.5 text-[13px] font-semibold">Where do your room calendars live?</legend>
            <div class="grid gap-2.5 sm:grid-cols-2">
                <OptionCard v-for="option in options.calendar_provider" :key="option.value" :selected="form.calendar_provider === option.value"
                            @select="form.calendar_provider = option.value">
                    <span class="font-medium">{{ option.label }}</span>
                </OptionCard>
            </div>
        </fieldset>

        <StepFooter :previous="previous" :processing="form.processing"
                    @back="onboarding.goTo(previous)" @skip="skip" @skip-setup="$emit('skip-setup')" @continue="submit" />
    </form>
</template>
