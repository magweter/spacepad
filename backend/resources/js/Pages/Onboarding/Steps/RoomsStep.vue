<script setup>
import { computed } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import ChoiceCard from '../../../Components/ChoiceCard.vue';
import ChoicePill from '../../../Components/ChoicePill.vue';
import StepHeading from '../../../Components/StepHeading.vue';
import Button from '../../../DesignSystem/Button.vue';
import Notice from '../../../DesignSystem/Notice.vue';

defineOptions({ inheritAttrs: false });

const props = defineProps({ answers: Object, options: Object, urls: Object, eyebrow: String, onboarding: Object });

const form = useForm({
    previous_solution: props.answers.previous_solution,
    previous_solution_text: props.answers.previous_solution_text ?? '',
    rooms: props.answers.rooms,
    locations: props.answers.locations,
});

const switching = computed(() => ['joan', 'teams_panels', 'robin_or_suite'].includes(form.previous_solution));
const toolLabel = computed(() => props.options.previous_solution.find((o) => o.value === form.previous_solution)?.label);
const multiSite = computed(() => ['several_buildings', 'several_countries'].includes(form.locations));

function submit() {
    form.post(props.urls.rooms, { preserveScroll: true });
}
</script>

<template>
    <StepHeading :eyebrow="eyebrow" title="Tell us about your rooms.">
        This decides the plan we suggest, how we group your displays and whether we show a migration guide.
    </StepHeading>

    <form class="flex flex-col gap-6" @submit.prevent="submit">
        <div class="flex flex-col gap-2.5">
            <span class="text-[13px] font-semibold">Today we use…</span>
            <div class="flex flex-wrap gap-2">
                <ChoicePill v-for="option in options.previous_solution" :key="option.value" :selected="form.previous_solution === option.value"
                            @select="form.previous_solution = option.value">{{ option.label }}</ChoicePill>
            </div>
            <input v-if="form.previous_solution === 'other'" v-model="form.previous_solution_text" type="text" maxlength="255" placeholder="Name of the product"
                   class="max-w-sm rounded-[10px] border border-offline-border bg-surface px-3.5 py-[11px] text-sm focus:border-navy focus:outline-none">
            <span v-if="form.errors.previous_solution_text" class="text-xs text-reserved-text">{{ form.errors.previous_solution_text }}</span>
            <Notice v-if="switching" tone="success" :title="`Switching from ${toolLabel}? You can run both side by side while you move rooms over.`" />
        </div>

        <div class="flex flex-col gap-2.5">
            <span class="text-[13px] font-semibold">How many rooms do you want on a display?</span>
            <div class="grid grid-cols-2 gap-2.5 sm:grid-cols-4">
                <ChoiceCard v-for="option in options.rooms" :key="option.value" :selected="form.rooms === option.value" @select="form.rooms = option.value">
                    <span class="flex flex-col gap-0.5"><span class="text-base font-bold">{{ option.label }}</span><span class="text-xs text-muted">{{ option.description }}</span></span>
                </ChoiceCard>
            </div>
        </div>

        <div class="flex flex-col gap-2.5">
            <span class="text-[13px] font-semibold">Where are those rooms?</span>
            <div class="grid gap-2.5 sm:grid-cols-3">
                <ChoiceCard v-for="option in options.locations" :key="option.value" :selected="form.locations === option.value" @select="form.locations = option.value">
                    <span class="flex flex-col gap-0.5"><span class="text-[15px] font-bold">{{ option.label }}</span><span class="text-xs text-muted">{{ option.description }}</span></span>
                </ChoiceCard>
            </div>
            <Notice v-if="multiSite" tone="neutral" title="We can group displays by building and floor, so a board shows one building. Several buildings in different countries usually means Enterprise; we will say so, not push it." />
        </div>

        <div class="flex items-center justify-between">
            <button type="button" class="text-[13px] font-semibold text-muted hover:text-navy" @click="onboarding.goTo('workspace')">← Back</button>
            <div class="flex items-center gap-2">
                <Button variant="ghost" size="lg" @click="router.post(urls.rooms, { skip: true })">Skip</Button>
                <Button type="submit" size="lg" :disabled="form.processing">Continue</Button>
            </div>
        </div>
    </form>
</template>
