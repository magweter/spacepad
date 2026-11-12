<script setup>
// The upgrade dialog. Every Pro label and every locked action in the portal opens it, on the
// feature that was clicked: a realistic picture of that feature, then Pro and Enterprise side by
// side so people pick for themselves. Both start with the 14-day trial; Enterprise also tells
// sales, who get in touch about a demo, an SLA and a quote.
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import Modal from '../../DesignSystem/Modal.vue';
import Icon from '../Icon.vue';
import FeatureShowcase from './FeatureShowcase.vue';

const page = usePage();
const upgrade = computed(() => page.props.upgrade);

const features = [
    { key: 'displays', label: 'Every room', caption: 'A display next to every meeting room. The free plan shows one; Pro has no limit.' },
    { key: 'configure', label: 'Your own look', caption: 'Your background, logo, texts and buttons, with check-in and booking at the door.' },
    { key: 'boards', label: 'Boards', caption: 'Every room on the lobby TV, grouped per floor, shared with a public link.' },
    { key: 'profiles', label: 'Profiles', caption: 'Set up the look and rules once and every linked display follows.' },
    { key: 'team', label: 'Your team', caption: 'Colleagues manage the same displays, boards and calendars, at no cost per person.' },
];
const pro = [
    'A display for every room',
    'Your own background, logo, theme and texts',
    'Check-in, booking and extending at the door',
    'Boards for the lobby and the hallway',
    'Profiles to set up many displays at once',
    'Colleagues as admin or member',
];
const enterprise = [
    'Everything in Pro',
    'A demo with your own rooms and calendars',
    'An SLA with agreed response times',
    'Help with the rollout, together with your IT',
    'A quote for your size, paid by invoice',
];

const open = ref(false);
const selected = ref('displays');
const current = computed(() => features.find((f) => f.key === selected.value) ?? features[0]);

const onOpen = (e) => {
    const key = e.detail?.feature;
    selected.value = features.some((f) => f.key === key) ? key : 'displays';
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
const contact = computed(() => `mailto:${upgrade.value?.salesEmail}?subject=${encodeURIComponent('Spacepad Enterprise')}`);
const trialLabel = computed(() => (upgrade.value?.trial ? 'Start 14-day free trial' : 'Upgrade to Pro'));
</script>

<template>
    <Modal v-if="upgrade" :open="open" title="Get more out of Spacepad" width="1040px" @close="open = false">
        <!-- What a feature looks like -->
        <div class="flex flex-col gap-3">
            <div class="flex flex-wrap gap-1.5" role="tablist" aria-label="Pro features">
                <button v-for="f in features" :key="f.key" type="button" role="tab" :aria-selected="selected === f.key" @click="selected = f.key"
                        class="rounded-full border px-3 py-1.5 text-[13px] font-semibold transition-colors"
                        :class="selected === f.key ? 'border-navy bg-navy text-white' : 'border-line bg-surface text-ink-2 hover:border-navy-400'">
                    {{ f.label }}
                </button>
            </div>
            <FeatureShowcase :feature="current.key" />
            <p class="text-[13px] text-ink-3">{{ current.caption }}</p>
        </div>

        <!-- Pro and Enterprise side by side -->
        <div class="grid gap-4 md:grid-cols-2">
            <section class="flex flex-col gap-4 rounded-xl border-2 border-info-border bg-surface p-5">
                <div class="flex flex-col gap-1">
                    <span class="self-start rounded-md border border-info-border bg-info-bg px-1.5 py-px text-[11px] font-semibold text-info">Pro</span>
                    <h3 class="font-display text-xl font-bold tracking-tight">{{ upgrade.selfHosted ? 'Pro on your own server' : upgrade.trial ? 'Try 14 days for free' : 'Pro' }}</h3>
                    <p class="text-[13px] text-muted">{{ upgrade.selfHosted ? 'Unlocked with a licence key.' : 'Billed per display, never per user. Cancel any time.' }}</p>
                </div>
                <ul class="flex flex-1 flex-col gap-2 text-[13px]">
                    <li v-for="item in pro" :key="item" class="flex items-start gap-2">
                        <span class="mt-0.5 flex h-4 w-4 flex-none items-center justify-center rounded-full bg-info-bg text-info"><Icon name="check" :size="10" :stroke="3" /></span>{{ item }}
                    </li>
                </ul>
                <a v-if="upgrade.selfHosted" :href="upgrade.urls.billing" class="sp-btn sp-btn-primary sp-btn-md justify-center">Enter licence key</a>
                <form v-else-if="upgrade.canManage" method="POST" :action="upgrade.urls.checkout">
                    <input type="hidden" name="_token" :value="csrf()">
                    <button type="submit" class="sp-btn sp-btn-primary sp-btn-md w-full justify-center">{{ trialLabel }}</button>
                </form>
                <p v-else class="text-[13px] text-muted">Ask {{ askList }} to start Pro. Only owners and admins can upgrade.</p>
            </section>

            <section class="flex flex-col gap-4 rounded-xl border border-line bg-surface-raised p-5">
                <div class="flex flex-col gap-1">
                    <span class="sp-chip sp-chip-amber self-start !text-[11px]">Enterprise</span>
                    <h3 class="font-display text-xl font-bold tracking-tight">Pro, with us by your side</h3>
                    <p class="text-[13px] text-muted">{{ upgrade.selfHosted ? 'For larger organisations on their own servers.' : 'Start with the same trial. We get in touch to plan the rest.' }}</p>
                </div>
                <ul class="flex flex-1 flex-col gap-2 text-[13px]">
                    <li v-for="item in enterprise" :key="item" class="flex items-start gap-2">
                        <span class="mt-0.5 flex h-4 w-4 flex-none items-center justify-center rounded-full bg-navy-50 text-navy"><Icon name="check" :size="10" :stroke="3" /></span>{{ item }}
                    </li>
                </ul>
                <div class="flex flex-col gap-2">
                    <form v-if="upgrade.canManage && !upgrade.selfHosted" method="POST" :action="upgrade.urls.enterprise">
                        <input type="hidden" name="_token" :value="csrf()">
                        <button type="submit" class="sp-btn sp-btn-secondary sp-btn-md w-full justify-center">{{ upgrade.trial ? 'Start the trial, and contact me' : 'Contact me about Enterprise' }}</button>
                    </form>
                    <a v-if="upgrade.salesEmail" :href="contact" class="self-center text-[13px] font-semibold text-navy hover:underline">Or email us first</a>
                </div>
            </section>
        </div>
    </Modal>
</template>
