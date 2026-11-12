<script setup>
// The upgrade dialog. Every Pro label and every locked action in the portal opens it. A toggle at
// the top picks the size of the office: up to 25 displays is Pro (a 14-day trial for owners and
// admins), 25 or more is Enterprise (a demo, an SLA, invoices). Each side says what it includes.
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import Modal from '../../DesignSystem/Modal.vue';
import Icon from '../Icon.vue';
import ProShowcase from './ProShowcase.vue';

const page = usePage();
const upgrade = computed(() => page.props.upgrade);

const pro = [
    { key: 'displays', title: 'A display for every room', body: 'The free plan shows one. Pro has no limit.' },
    { key: 'configure', title: 'Your own look and rules', body: 'Background, logo, theme and texts, check-in against no-shows, booking and extending at the door.' },
    { key: 'boards', title: 'Boards on the lobby TV', body: 'Every room at once, shared with a public link.' },
    { key: 'profiles', title: 'Profiles', body: 'Set up many displays at once and keep them in step.' },
    { key: 'team', title: 'Your team', body: 'Invite colleagues as admin or member, at no cost per person.' },
];
const enterprise = [
    { title: 'Everything in Pro', body: 'For every office, building and floor.' },
    { title: 'A demo with your own rooms', body: 'We set up Spacepad with your calendar and walk your team through it.' },
    { title: 'An SLA', body: 'Agreed response times and support you can count on.' },
    { title: 'Help with the rollout', body: 'Connecting the calendars and setting up the rooms, together with your IT.' },
    { title: 'Pricing for your size, paid by invoice', body: 'A quote for your number of displays instead of a card payment.' },
];

const open = ref(false);
const tier = ref('pro');
const highlight = ref('displays');

const onOpen = (e) => {
    const key = e.detail?.feature;
    highlight.value = pro.some((f) => f.key === key) ? key : 'displays';
    tier.value = 'pro';
    open.value = true;
};
onMounted(() => window.addEventListener('open-upgrade', onOpen));
onBeforeUnmount(() => window.removeEventListener('open-upgrade', onOpen));

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';
const askList = computed(() => {
    const names = upgrade.value?.managers ?? [];
    if (!names.length) return 'an owner or admin of this workspace';
    return names.length === 1 ? names[0] : `${names.slice(0, -1).join(', ')} or ${names.at(-1)}`;
});
const mail = (subject) => `mailto:${upgrade.value?.salesEmail}?subject=${encodeURIComponent(subject)}`;

const proChip = 'rounded-md border border-info-border bg-info-bg px-1.5 py-px text-[11px] font-semibold text-info';
const enterpriseChip = 'rounded-md border border-amber-300 bg-amber-50 px-1.5 py-px text-[11px] font-semibold text-checkin-text';
</script>

<template>
    <Modal v-if="upgrade" :open="open" title="Get more out of Spacepad" width="960px" @close="open = false">
        <!-- Size of the office -->
        <div v-if="!upgrade.selfHosted" class="inline-flex self-start rounded-lg border border-line bg-surface-raised p-1" role="tablist" aria-label="Size of your office">
            <button type="button" role="tab" :aria-selected="tier === 'pro'" @click="tier = 'pro'"
                    class="inline-flex items-center gap-2 rounded-md px-3 py-1.5 text-[13px] font-semibold transition-colors"
                    :class="tier === 'pro' ? 'bg-surface text-ink shadow-sm' : 'text-muted hover:text-ink'">
                Up to 25 displays <span :class="proChip">Pro</span>
            </button>
            <button type="button" role="tab" :aria-selected="tier === 'enterprise'" @click="tier = 'enterprise'"
                    class="inline-flex items-center gap-2 rounded-md px-3 py-1.5 text-[13px] font-semibold transition-colors"
                    :class="tier === 'enterprise' ? 'bg-surface text-ink shadow-sm' : 'text-muted hover:text-ink'">
                25 displays or more <span :class="enterpriseChip">Enterprise</span>
            </button>
        </div>

        <div class="grid gap-6 md:grid-cols-[1.15fr_1fr]">
            <ProShowcase :enterprise="tier === 'enterprise'" />

            <!-- Pro -->
            <div v-if="tier === 'pro'" class="flex min-w-0 flex-col gap-4">
                <div class="flex flex-col gap-1">
                    <span :class="proChip" class="self-start">Pro</span>
                    <h3 class="font-display text-2xl leading-tight font-bold tracking-tight">
                        {{ upgrade.selfHosted ? 'Pro on your own server' : upgrade.trial ? 'Try 14 days for free' : 'Everything your rooms need' }}
                    </h3>
                    <p class="text-[13px] text-muted">{{ upgrade.selfHosted ? 'Unlocked with a licence key.' : 'Billed per display, never per user. Cancel any time.' }}</p>
                </div>
                <ul class="flex flex-col gap-2.5">
                    <li v-for="f in pro" :key="f.key" class="flex gap-2.5 rounded-lg px-2 py-1.5 -mx-2" :class="f.key === highlight ? 'bg-info-bg' : ''">
                        <span class="mt-0.5 flex h-[18px] w-[18px] flex-none items-center justify-center rounded-full bg-available-bg text-available-text"><Icon name="check" :size="11" :stroke="3" /></span>
                        <span class="flex flex-col"><span class="text-[13px] font-semibold">{{ f.title }}</span><span class="text-xs text-muted">{{ f.body }}</span></span>
                    </li>
                </ul>
            </div>

            <!-- Enterprise -->
            <div v-else class="flex min-w-0 flex-col gap-4">
                <div class="flex flex-col gap-1">
                    <span :class="enterpriseChip" class="self-start">Enterprise</span>
                    <h3 class="font-display text-2xl leading-tight font-bold tracking-tight">For 25 displays or more</h3>
                    <p class="text-[13px] text-muted">For organisations with several offices or a large building.</p>
                </div>
                <ul class="flex flex-col gap-2.5">
                    <li v-for="f in enterprise" :key="f.title" class="flex gap-2.5">
                        <span class="mt-0.5 flex h-[18px] w-[18px] flex-none items-center justify-center rounded-full bg-amber-50 text-checkin-text"><Icon name="check" :size="11" :stroke="3" /></span>
                        <span class="flex flex-col"><span class="text-[13px] font-semibold">{{ f.title }}</span><span class="text-xs text-muted">{{ f.body }}</span></span>
                    </li>
                </ul>
            </div>
        </div>

        <!-- How to get it -->
        <div class="flex flex-wrap items-center gap-3 rounded-xl border border-line bg-surface-raised p-4">
            <template v-if="tier === 'enterprise'">
                <span class="flex min-w-0 flex-1 flex-col">
                    <span class="font-semibold">See it with your own rooms first</span>
                    <span class="text-[13px] text-muted">We plan a demo with you and send a quote for your number of displays.</span>
                </span>
                <a :href="mail('Spacepad Enterprise: question')" class="sp-btn sp-btn-ghost sp-btn-md">Ask a question</a>
                <a :href="mail('Spacepad Enterprise: book a demo')" class="sp-btn sp-btn-primary sp-btn-md">Book a demo</a>
            </template>
            <template v-else-if="upgrade.selfHosted">
                <span class="flex min-w-0 flex-1 flex-col">
                    <span class="font-semibold">Enter your licence key</span>
                    <span class="text-[13px] text-muted">It unlocks every Pro feature on this instance.</span>
                </span>
                <a :href="upgrade.urls.billing" class="sp-btn sp-btn-accent sp-btn-md">Enter licence key</a>
            </template>
            <template v-else-if="upgrade.canManage">
                <span class="flex min-w-0 flex-1 flex-col">
                    <span class="font-semibold">{{ upgrade.trial ? '14 days free, then billed per display' : 'Upgrade this workspace to Pro' }}</span>
                    <span class="text-[13px] text-muted">{{ upgrade.trial ? 'Cancel any time during the trial and you pay nothing.' : 'Billed per display, never per user.' }}</span>
                </span>
                <a :href="upgrade.urls.billing" class="sp-btn sp-btn-ghost sp-btn-md">See billing</a>
                <form method="POST" :action="upgrade.urls.checkout">
                    <input type="hidden" name="_token" :value="csrf()">
                    <button type="submit" class="sp-btn sp-btn-accent sp-btn-md">{{ upgrade.trial ? 'Start 14-day free trial' : 'Upgrade to Pro' }}</button>
                </form>
            </template>
            <template v-else>
                <span class="flex min-w-0 flex-1 flex-col">
                    <span class="font-semibold">Ask {{ askList }} to start Pro</span>
                    <span class="text-[13px] text-muted">Only owners and admins can upgrade a workspace.</span>
                </span>
            </template>
        </div>
    </Modal>
</template>
