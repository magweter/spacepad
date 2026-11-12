<script setup>
// The upgrade dialog. Every Pro label and every locked action in the portal opens it, on the
// feature that was clicked: what Pro adds, with a drawing, and the way to get it: a trial for
// owners and admins, whom to ask for members, the licence for self-hosted, Enterprise above 25.
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import Modal from '../../DesignSystem/Modal.vue';
import Icon from '../Icon.vue';
import FeatureVisual from './FeatureVisual.vue';

const page = usePage();
const upgrade = computed(() => page.props.upgrade);

const features = [
    { key: 'displays', title: 'A display for every room', body: 'The free plan shows one display. With Pro every meeting room gets one, all managed from this portal.' },
    { key: 'configure', title: 'Displays that fit your office', body: 'Check-in against no-shows, booking and extending from the tablet, your own texts, logo, background and theme.' },
    { key: 'boards', title: 'Every room on one screen', body: 'A board on a TV in the lobby or the hallway shows which rooms are free right now. Share it with a public link.' },
    { key: 'profiles', title: 'Set up many displays at once', body: 'Save your settings as a profile and link displays to it. Change the profile and every display follows.' },
    { key: 'team', title: 'Your whole team in one workspace', body: 'Invite colleagues as admin or member to manage the same displays, boards and calendars.' },
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
const enterpriseMail = computed(() => `mailto:${upgrade.value?.salesEmail}?subject=${encodeURIComponent('Spacepad Enterprise or a demo')}`);
</script>

<template>
    <Modal v-if="upgrade" :open="open" title="Get more out of Spacepad with Pro" width="880px" @close="open = false">
        <div class="grid gap-6 md:grid-cols-[260px_1fr]">
            <ul class="flex flex-col gap-1" role="tablist" aria-label="What Pro adds">
                <li v-for="f in features" :key="f.key">
                    <button type="button" role="tab" :aria-selected="selected === f.key" @click="selected = f.key"
                            class="flex w-full items-center gap-2.5 rounded-lg px-3 py-2.5 text-left text-[13px] font-semibold transition-colors"
                            :class="selected === f.key ? 'bg-navy text-white' : 'text-ink-2 hover:bg-surface-raised'">
                        <span class="flex h-5 w-5 flex-none items-center justify-center rounded-full" :class="selected === f.key ? 'bg-amber text-navy' : 'bg-available-bg text-available-text'">
                            <Icon name="check" :size="12" :stroke="3" />
                        </span>
                        {{ f.title }}
                    </button>
                </li>
            </ul>

            <div class="flex min-w-0 flex-col gap-3">
                <FeatureVisual :feature="current.key" />
                <h3 class="font-display text-lg font-bold tracking-tight">{{ current.title }}</h3>
                <p class="text-ink-3">{{ current.body }}</p>
            </div>
        </div>

        <!-- How to get it -->
        <div class="flex flex-wrap items-center gap-3 rounded-xl border border-line bg-surface-raised p-4">
            <template v-if="upgrade.selfHosted">
                <span class="flex min-w-0 flex-1 flex-col">
                    <span class="font-semibold">Pro on your own server comes with a licence key</span>
                    <span class="text-[13px] text-muted">Enter it on the billing page to unlock every Pro feature on this instance.</span>
                </span>
                <a :href="upgrade.urls.billing" class="sp-btn sp-btn-accent sp-btn-md">Enter licence key</a>
            </template>
            <template v-else-if="upgrade.canManage">
                <span class="flex min-w-0 flex-1 flex-col">
                    <span class="font-semibold">{{ upgrade.trial ? 'Try every Pro feature, free' : 'Upgrade this workspace to Pro' }}</span>
                    <span class="text-[13px] text-muted">Billed per display, never per user. Cancel any time.</span>
                </span>
                <a :href="upgrade.urls.billing" class="sp-btn sp-btn-ghost sp-btn-md">See billing</a>
                <form method="POST" :action="upgrade.urls.checkout">
                    <input type="hidden" name="_token" :value="csrf()">
                    <button type="submit" class="sp-btn sp-btn-accent sp-btn-md">{{ upgrade.trial ? 'Start free trial' : 'Upgrade to Pro' }}</button>
                </form>
            </template>
            <template v-else>
                <span class="flex min-w-0 flex-1 flex-col">
                    <span class="font-semibold">Ask {{ askList }} to start Pro</span>
                    <span class="text-[13px] text-muted">Only owners and admins can upgrade a workspace.</span>
                </span>
            </template>
        </div>

        <p v-if="upgrade.salesEmail && !upgrade.selfHosted" class="text-[13px] text-muted">
            25 displays or more, or want to see it first? <a :href="enterpriseMail" class="font-semibold text-navy hover:underline">Contact us about Enterprise or a demo</a>.
        </p>
    </Modal>
</template>
