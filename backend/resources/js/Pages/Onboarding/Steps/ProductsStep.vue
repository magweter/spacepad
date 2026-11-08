<script setup>
import { computed } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import OptionCard from '../../../Components/OptionCard.vue';
import StepFooter from '../../../Components/StepFooter.vue';

defineOptions({ inheritAttrs: false });

const props = defineProps({
    answers: Object, options: Object, urls: Object, next: String, previous: String, onboarding: Object,
});
defineEmits(['skip-setup']);

const form = useForm({ products: props.answers.products?.length ? [...props.answers.products] : ['display'] });

const names = { display: 'Display', board: 'Board', console: 'Console' };
const summary = computed(() => form.products.map((p) => names[p]).join(' + ') || 'Display');

function toggle(value) {
    form.products = form.products.includes(value) ? form.products.filter((p) => p !== value) : [...form.products, value];
}

function submit() {
    form.post(props.urls.products, { preserveScroll: true });
}

function skip() {
    router.post(props.urls.products, { skip: true });
}
</script>

<template>
    <div class="flex flex-col gap-2">
        <h1 class="text-[28px] leading-tight font-bold tracking-tight">Where do you want Spacepad to show up?</h1>
        <p class="text-muted">Pick everything that sounds useful. It helps us point you to the right next step.</p>
    </div>

    <div class="flex flex-col gap-3">
        <OptionCard v-for="option in options.products" :key="option.value" type="checkbox"
                    :selected="form.products.includes(option.value)" @select="toggle(option.value)">
            <span class="text-[11px] font-bold tracking-[0.04em] uppercase"
                  :class="{ 'text-amber-text': option.value === 'display', 'text-info': option.value === 'board', 'text-purple-800': option.value === 'console' }">{{ option.tag }}</span>
            <span class="font-semibold">{{ option.label }}</span>
            <span class="text-[13px] text-muted">{{ option.description }}</span>
        </OptionCard>
    </div>

    <StepFooter :previous="previous" :processing="form.processing" :continue-label="`Continue with ${summary}`"
                @back="onboarding.goTo(previous)" @skip="skip" @skip-setup="$emit('skip-setup')" @continue="submit" />
</template>
