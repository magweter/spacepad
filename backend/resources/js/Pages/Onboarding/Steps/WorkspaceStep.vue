<script setup>
import { computed, ref } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import ChoiceCard from '../../../Components/ChoiceCard.vue';
import StepHeading from '../../../Components/StepHeading.vue';
import Button from '../../../DesignSystem/Button.vue';

defineOptions({ inheritAttrs: false });

const props = defineProps({ user: Object, workspace: Object, urls: Object, eyebrow: String, onboarding: Object });

const page = usePage();
const selfHosted = computed(() => page.props.app.selfHosted);

const form = useForm({
    usage_type: props.user.usageType ?? 'business',
    workspace_name: props.workspace.name ?? '',
    accept_terms: false,
});

const inviteOpen = ref(false);
const invite = ref('');
const inviteError = ref('');

function submit() {
    form.post(props.urls.workspace, { preserveScroll: true });
}

function openInvite() {
    const link = invite.value.trim();
    if (!link.startsWith(`${props.urls.invitationBase}/`)) {
        inviteError.value = 'That is not a Spacepad invite link. It starts with ' + props.urls.invitationBase + '/';
        return;
    }
    window.location.href = link;
}

function signOut() {
    router.post(props.urls.logout);
}
</script>

<template>
    <StepHeading :eyebrow="eyebrow" :title="`Welcome, ${user.firstName}. How will you use Spacepad?`">
        We tailor the setup to your situation. Signed in as {{ user.email }}.
        <button type="button" class="font-semibold text-navy hover:underline" @click="signOut">Not you?</button>
    </StepHeading>

    <form class="flex flex-col gap-6" @submit.prevent="submit">
        <div class="grid gap-3 sm:grid-cols-2">
            <ChoiceCard size="lg" :selected="form.usage_type === 'business'" @select="form.usage_type = 'business'">
                <span class="flex h-10 w-10 items-center justify-center rounded-[10px] bg-info-bg text-info">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="7" width="18" height="14" rx="2" /><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M3 13h18" /></svg>
                </span>
                <span class="flex flex-col gap-[3px]">
                    <span class="text-base font-bold">For my organisation</span>
                    <span class="text-[13px] text-muted">Meeting rooms at an office, school or venue, shared with colleagues.</span>
                </span>
            </ChoiceCard>
            <ChoiceCard size="lg" :selected="form.usage_type === 'personal'" @select="form.usage_type = 'personal'">
                <span class="flex h-10 w-10 items-center justify-center rounded-[10px] bg-available-bg text-available-text">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m3 11 9-8 9 8v10a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z" /></svg>
                </span>
                <span class="flex flex-col gap-[3px]">
                    <span class="text-base font-bold">Personal or hobby</span>
                    <span class="text-[13px] text-muted">A home office, a club or a tinkering project. Just you, or a few people.</span>
                </span>
            </ChoiceCard>
        </div>
        <span v-if="form.errors.usage_type" class="text-xs text-reserved-text">{{ form.errors.usage_type }}</span>

        <label v-if="form.usage_type === 'business' && workspace.canRename" class="flex flex-col gap-1.5">
            <span class="text-[13px] font-semibold">Workspace name</span>
            <input v-model="form.workspace_name" type="text" placeholder="Acme HQ, Amsterdam"
                   class="rounded-[10px] border border-offline-border bg-surface px-3.5 py-[11px] text-sm text-ink focus:border-navy focus:outline-none">
            <span class="text-xs text-muted">Usually your company or office location. Change this later under Team.</span>
        </label>

        <label v-if="selfHosted && !user.termsAccepted" class="flex items-start gap-3 text-[13px]">
            <input v-model="form.accept_terms" type="checkbox" class="mt-0.5 h-4 w-4 rounded border-offline-border">
            <span>I agree to the <a :href="urls.license" target="_blank" rel="noopener" class="font-semibold text-navy">licence terms</a>.</span>
        </label>
        <span v-if="form.errors.accept_terms" class="text-xs text-reserved-text">{{ form.errors.accept_terms }}</span>

        <div v-if="inviteOpen" class="flex flex-col gap-1.5">
            <label for="invite" class="text-[13px] font-semibold">Invite link</label>
            <div class="flex gap-2">
                <input id="invite" v-model="invite" type="url" :placeholder="`${urls.invitationBase}/…`" @input="inviteError = ''"
                       class="min-w-0 flex-1 rounded-[10px] border border-offline-border bg-surface px-3.5 py-[11px] text-sm focus:border-navy focus:outline-none">
                <Button variant="secondary" @click="openInvite">Open</Button>
            </div>
            <span v-if="inviteError" class="text-xs text-reserved-text">{{ inviteError }}</span>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3">
            <span v-if="!inviteOpen" class="text-xs text-muted">
                Already using Spacepad with colleagues?
                <button type="button" class="font-semibold text-navy hover:underline" @click="inviteOpen = true">Enter their invite link</button>
            </span>
            <span v-else></span>
            <Button type="submit" size="lg" :disabled="form.processing">Continue</Button>
        </div>
    </form>
</template>
