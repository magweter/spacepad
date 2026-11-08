<script setup>
import { computed } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import OptionCard from '../../../Components/OptionCard.vue';
import StepFooter from '../../../Components/StepFooter.vue';

defineOptions({ inheritAttrs: false });

const props = defineProps({
    user: Object, workspace: Object, urls: Object, next: String, previous: String, onboarding: Object,
});
defineEmits(['skip-setup']);

const page = usePage();
const selfHosted = computed(() => page.props.app.selfHosted);
const firstName = computed(() => (props.user.name || '').split(' ')[0]);

const form = useForm({
    usage_type: props.user.usageType ?? 'business',
    name: props.user.name ?? '',
    workspace_name: props.workspace.name ?? '',
    accept_terms: false,
});

function submit() {
    form.post(props.urls.workspace, { preserveScroll: true });
}
</script>

<template>
    <div class="flex flex-col gap-2">
        <h1 class="text-[28px] leading-tight font-bold tracking-tight">Welcome{{ firstName ? `, ${firstName}` : '' }}. How will you use Spacepad?</h1>
        <p class="text-muted">One question, so we show the right plan and skip what doesn't apply.</p>
    </div>

    <form class="flex flex-col gap-6" @submit.prevent="submit">
        <div class="grid gap-3 sm:grid-cols-2">
            <OptionCard :selected="form.usage_type === 'business'" @select="form.usage_type = 'business'">
                <span class="font-semibold">For my organisation</span>
                <span class="text-[13px] text-muted">Meeting rooms at the office. Team access, boards, profiles. First display free.</span>
            </OptionCard>
            <OptionCard :selected="form.usage_type === 'personal'" @select="form.usage_type = 'personal'">
                <span class="font-semibold">Personal or hobby</span>
                <span class="text-[13px] text-muted">A home office or tinkering project. Self-host without limits, free. Cloud with one free display.</span>
            </OptionCard>
        </div>
        <span v-if="form.errors.usage_type" class="text-xs text-red-700">{{ form.errors.usage_type }}</span>

        <div class="grid gap-4 sm:grid-cols-2">
            <label class="flex flex-col gap-1.5">
                <span class="text-[13px] font-medium">Your name</span>
                <input v-model="form.name" type="text" required autocomplete="name"
                       class="rounded-[10px] border border-slate-300 bg-white px-3.5 py-2.5 text-sm focus:border-navy focus:outline-none">
                <span v-if="form.errors.name" class="text-xs text-red-700">{{ form.errors.name }}</span>
            </label>
            <label v-if="workspace.canRename" class="flex flex-col gap-1.5">
                <span class="text-[13px] font-medium">Workspace name</span>
                <input v-model="form.workspace_name" type="text" placeholder="Acme HQ"
                       class="rounded-[10px] border border-slate-300 bg-white px-3.5 py-2.5 text-sm focus:border-navy focus:outline-none">
                <span class="text-xs text-muted">Usually your company or office location.</span>
            </label>
        </div>

        <div v-if="form.usage_type === 'personal' && !selfHosted" class="rounded-xl border border-line bg-white p-4 text-[13px] text-ink-3">
            <strong class="font-semibold text-ink">Free for personal use.</strong>
            One display in the cloud is free. If this ever becomes a business deployment, switch to a paid plan from Billing. We never lock your data.
        </div>

        <label v-if="selfHosted && !user.termsAccepted" class="flex items-start gap-3 text-[13px]">
            <input v-model="form.accept_terms" type="checkbox" class="mt-0.5 h-4 w-4 rounded border-slate-300 text-navy">
            <span>I agree to the <a href="https://github.com/magweter/spacepad/blob/main/LICENSE.md" target="_blank" rel="noopener" class="font-semibold text-navy">license terms</a>.</span>
        </label>
        <span v-if="form.errors.accept_terms" class="text-xs text-red-700">{{ form.errors.accept_terms }}</span>

        <StepFooter :previous="null" :processing="form.processing" :show-skip="false"
                    @skip-setup="$emit('skip-setup')" @continue="submit" />
    </form>
</template>
