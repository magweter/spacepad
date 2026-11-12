<script setup>
import { router, useForm } from '@inertiajs/vue3';
import ChoiceCard from '../../../Components/ChoiceCard.vue';
import StepHeading from '../../../Components/StepHeading.vue';
import Button from '../../../DesignSystem/Button.vue';
import Notice from '../../../DesignSystem/Notice.vue';

defineOptions({ inheritAttrs: false });

const props = defineProps({ answers: Object, options: Object, urls: Object, eyebrow: String, onboarding: Object });

const form = useForm({
    tablets: props.answers.tablets,
    features: props.answers.features?.length ? [...props.answers.features] : [],
});

function toggle(value) {
    form.features = form.features.includes(value) ? form.features.filter((f) => f !== value) : [...form.features, value];
}

function submit() {
    form.post(props.urls.tablets, { preserveScroll: true });
}
</script>

<template>
    <StepHeading :eyebrow="eyebrow" title="What will the displays run on?">
        This picks the pairing route we show. No tablets yet is fine; most teams start with the one in a drawer.
    </StepHeading>

    <form class="flex flex-col gap-6" @submit.prevent="submit">
        <div class="flex flex-col gap-2.5">
            <span class="text-[13px] font-semibold">We have…</span>
            <div class="grid grid-cols-2 gap-2.5 sm:grid-cols-4">
                <ChoiceCard v-for="option in options.tablets" :key="option.value" :selected="form.tablets === option.value" @select="form.tablets = option.value">
                    <span class="flex flex-col gap-0.5"><span class="text-[15px] font-bold">{{ option.label }}</span><span class="text-xs text-muted">{{ option.description }}</span></span>
                </ChoiceCard>
            </div>
            <Notice v-if="form.tablets === 'none'" tone="neutral" title="Any iPad from iOS 15 or Android tablet from Android 8 works. Set up the display now and pair a tablet when it arrives." />
        </div>

        <div class="flex flex-col gap-2.5">
            <span class="text-[13px] font-semibold">Which features matter to you? <span class="font-normal text-muted">Pick any</span></span>
            <div class="grid gap-2.5 sm:grid-cols-3">
                <ChoiceCard v-for="option in options.features" :key="option.value" type="checkbox" :selected="form.features.includes(option.value)" @select="toggle(option.value)">
                    <span class="flex min-w-0 flex-col gap-px"><span class="text-[13px] font-semibold">{{ option.label }}</span><span class="text-xs text-muted">{{ option.description }}</span></span>
                </ChoiceCard>
            </div>
        </div>

        <div class="flex items-center justify-between">
            <button type="button" class="text-[13px] font-semibold text-muted hover:text-navy" @click="onboarding.goTo('rooms')">← Back</button>
            <div class="flex items-center gap-2">
                <Button variant="ghost" size="lg" @click="router.post(urls.tablets, { skip: true })">Skip this step</Button>
                <Button type="submit" size="lg" :disabled="(!form.tablets && !form.features.length) || form.processing">Continue</Button>
            </div>
        </div>
    </form>
</template>
