<script setup>
// Step 3, the current setup: the tablets the displays will run on (so we show the right pairing
// steps) and what they use today (so we can help them move over).
import Icon from '../../../Components/Icon.vue';
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
    tablets: props.answers.tablets,
    hardware_guide: props.answers.hardware_guide === 'yes',
    previous_solution: props.answers.previous_solution,
    previous_solution_text: props.answers.previous_solution_text ?? '',
});

const switching = computed(() => ['joan', 'robin', 'teams_panels', 'pronestor', 'appspace', 'eptura'].includes(form.previous_solution));
const toolLabel = computed(() => props.options.previous_solution.find((o) => o.value === form.previous_solution)?.label);

function submit() {
    form.post(props.urls.tablets, { preserveScroll: true });
}
</script>

<template>
    <StepHeading :eyebrow="eyebrow" title="What do you have today?">
        So we show the right pairing steps for your tablets, and help you move over from what you use now. No tablets yet is fine.
    </StepHeading>

    <form class="flex flex-col gap-6" @submit.prevent="submit">
        <div class="flex flex-col gap-2.5">
            <span class="text-[13px] font-semibold">The displays will run on…</span>
            <div class="grid grid-cols-2 gap-2.5 sm:grid-cols-4">
                <ChoiceCard v-for="option in options.tablets" :key="option.value" :selected="form.tablets === option.value" @select="form.tablets = option.value">
                    <span class="flex flex-col gap-0.5"><span class="text-[15px] font-bold">{{ option.label }}</span><span class="text-xs text-muted">{{ option.description }}</span></span>
                </ChoiceCard>
            </div>
            <template v-if="form.tablets === 'none'">
                <Notice tone="neutral" title="Any iPad on iPadOS 15 or newer and any Android tablet on Android 7 or newer works. Set up the display now and pair a tablet when it arrives." />
                <ChoiceCard type="checkbox" :selected="form.hardware_guide" @select="form.hardware_guide = !form.hardware_guide">
                    <span class="flex min-w-0 flex-col gap-px"><span class="text-[13px] font-semibold">Send me the hardware guide</span><span class="text-xs text-muted">The tablets and wall mounts we suggest, by email.</span></span>
                </ChoiceCard>
            </template>
        </div>

        <div class="flex flex-col gap-2.5">
            <span class="text-[13px] font-semibold">For room displays we use…</span>
            <div class="flex flex-wrap gap-2">
                <ChoicePill v-for="option in options.previous_solution" :key="option.value" :selected="form.previous_solution === option.value"
                            @select="form.previous_solution = option.value">{{ option.label }}</ChoicePill>
            </div>
            <input v-if="form.previous_solution === 'other'" v-model="form.previous_solution_text" type="text" maxlength="255" placeholder="Name of the product"
                   class="max-w-sm rounded-md border border-offline-border bg-surface px-3.5 py-[11px] text-sm focus:border-navy focus:outline-none">
            <span v-if="form.errors.previous_solution_text" class="text-xs text-reserved-text">{{ form.errors.previous_solution_text }}</span>
            <Notice v-if="switching" tone="success" :title="`Switching from ${toolLabel}? You can run both side by side while you move rooms over, and we are happy to help.`" />
        </div>

        <div class="flex items-center justify-between">
            <Button variant="ghost" @click="onboarding.goTo('rooms')"><template #icon><Icon name="arrowLeft" :size="16" /></template>Back</Button>
            <div class="flex items-center gap-2">
                <Button variant="ghost" @click="router.post(urls.tablets, { skip: true })">Skip this step</Button>
                <Button type="submit" :disabled="(!form.tablets && !form.previous_solution) || form.processing">Continue</Button>
            </div>
        </div>
    </form>
</template>
