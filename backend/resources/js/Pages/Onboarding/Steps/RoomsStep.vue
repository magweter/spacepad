<script setup>
// Step 2, the organisation: how many rooms, where they are and what they need. It shapes the plan
// and setup we suggest; the current setup (tablets, what they use now) is step 3.
import Icon from '../../../Components/Icon.vue';
import { computed } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import ChoiceCard from '../../../Components/ChoiceCard.vue';
import StepHeading from '../../../Components/StepHeading.vue';
import Button from '../../../DesignSystem/Button.vue';
import Notice from '../../../DesignSystem/Notice.vue';

defineOptions({ inheritAttrs: false });

const props = defineProps({ answers: Object, options: Object, urls: Object, eyebrow: String, onboarding: Object });

const form = useForm({
    rooms: props.answers.rooms,
    locations: props.answers.locations,
    features: props.answers.features?.length ? [...props.answers.features] : [],
});

const multiSite = computed(() => ['several_buildings', 'several_countries'].includes(form.locations));

function toggle(value) {
    form.features = form.features.includes(value) ? form.features.filter((f) => f !== value) : [...form.features, value];
}

function submit() {
    form.post(props.urls.rooms, { preserveScroll: true });
}
</script>

<template>
    <StepHeading :eyebrow="eyebrow" title="What does your organisation need?">
        A few quick answers, so we can suggest the plan and setup that fit.
    </StepHeading>

    <form class="flex flex-col gap-6" @submit.prevent="submit">
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
            <Notice v-if="multiSite" tone="neutral" title="We can group displays by building and floor, so a board shows one building." />
        </div>

        <div class="flex flex-col gap-2.5">
            <span class="text-[13px] font-semibold">What do you require?</span>
            <div class="grid gap-2.5 sm:grid-cols-3">
                <ChoiceCard v-for="option in options.features" :key="option.value" type="checkbox" :selected="form.features.includes(option.value)" @select="toggle(option.value)">
                    <span class="flex min-w-0 flex-col gap-px"><span class="text-[13px] font-semibold">{{ option.label }}</span><span class="text-xs text-muted">{{ option.description }}</span></span>
                </ChoiceCard>
            </div>
        </div>

        <div class="flex items-center justify-between">
            <Button variant="ghost" @click="onboarding.goTo('workspace')"><template #icon><Icon name="arrowLeft" :size="16" /></template>Back</Button>
            <div class="flex items-center gap-2">
                <Button variant="ghost" @click="router.post(urls.rooms, { skip: true })">Skip this step</Button>
                <Button type="submit" :disabled="(!form.rooms && !form.locations && !form.features.length) || form.processing">Continue</Button>
            </div>
        </div>
    </form>
</template>
