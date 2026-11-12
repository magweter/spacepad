<script setup>
// The current plan on navy, as in the design: what the workspace is on, what it uses and costs,
// and the one thing an owner or admin is most likely to do next.
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';
import Icon from '../../../Components/Icon.vue';
import Button from '../../../DesignSystem/Button.vue';
import { postForm } from './postForm';

const props = defineProps({ billing: Object, urls: Object });
defineEmits(['manage']);

const plan = computed(() => {
    if (!props.billing.hasPro) return 'Free';
    return props.billing.selfHosted ? 'Self-hosted Pro' : 'Pro';
});
const chip = computed(() => {
    const i = props.billing.interval;
    if (!i) return null;
    return i.includes('yearly') ? 'Yearly' : 'Monthly';
});
const money = (n) => `${props.billing.currency}${Number(n).toFixed(2)}`;
const plural = (n, word) => `${n} ${word}${n === 1 ? '' : 's'}`;
const trialTitle = computed(() => {
    const d = props.billing.trial?.daysLeft;
    if (d === undefined) return null;
    if (d <= 0) return 'Your trial ends today';
    return d === 1 ? '1 day left of your trial' : `${d} days left of your trial`;
});
</script>

<template>
    <div class="flex flex-col gap-3.5 rounded-xl bg-navy p-5 text-white">
        <div class="flex items-start justify-between gap-3">
            <span class="flex flex-col gap-0.5">
                <span class="text-xs font-medium text-on-navy-muted">Current plan</span>
                <span class="font-display text-[22px] font-bold tracking-tight">{{ plan }}</span>
                <span v-if="billing.interval" class="text-xs text-on-navy-2">{{ billing.interval }}</span>
            </span>
            <span v-if="chip" class="rounded-full bg-amber px-2.5 py-0.5 text-[11px] font-bold tracking-wide text-navy">{{ chip }}</span>
        </div>

        <div class="flex flex-col gap-2 text-[13px]">
            <div class="flex justify-between"><span class="text-on-navy-2">Displays</span><span class="font-semibold">{{ billing.usage.displays }}</span></div>
            <div class="flex justify-between"><span class="text-on-navy-2">Boards</span><span class="font-semibold">{{ billing.usage.boards }}</span></div>
            <div class="flex justify-between"><span class="text-on-navy-2">Total units</span><span class="font-semibold">{{ billing.usage.total }}</span></div>
            <span v-if="billing.monthlyCost !== null" class="text-xs text-on-navy-muted">
                {{ plural(billing.usage.total, 'unit') }} at {{ money(billing.unitPrice) }} =
                <strong class="font-semibold text-white">{{ money(billing.monthlyCost) }} per month</strong>
            </span>
        </div>

        <div v-if="billing.trial" class="flex flex-col gap-0.5 border-t border-navy-700 pt-3 text-[13px]">
            <span class="font-semibold">{{ trialTitle }}</span>
            <span class="text-xs text-on-navy-2">Your subscription starts automatically on {{ billing.trial.endsOn }}. Nothing to do, and your displays keep running.</span>
        </div>

        <div v-if="billing.canManage && (['checkout', 'portal', 'modal'].includes(billing.state) || billing.switch)" class="flex flex-wrap gap-2 border-t border-navy-700 pt-3">
            <Button v-if="billing.state === 'checkout'" variant="accent" @click="postForm(urls.checkout)">Upgrade to Pro</Button>
            <Button v-else-if="billing.state === 'portal'" variant="accent" @click="postForm(urls.portal, { target: '_blank' })">
                Open billing portal <Icon name="external" :size="14" />
            </Button>
            <Button v-else-if="billing.state === 'modal'" variant="accent" @click="$emit('manage')">Manage subscription</Button>

            <button v-if="billing.switch?.to === 'yearly'" type="button" class="rounded-lg border border-navy-600 px-3.5 py-2 text-[13px] font-semibold text-white hover:bg-navy-800" @click="postForm(urls.switchYearly)">Switch to yearly</button>
            <button v-else-if="billing.switch?.to === 'monthly'" type="button" class="rounded-lg border border-navy-600 px-3.5 py-2 text-[13px] font-semibold text-white hover:bg-navy-800" @click="router.post(urls.switchMonthly)">Switch to monthly</button>
            <button v-else-if="billing.switch?.to === 'pending-monthly'" type="button" class="rounded-lg border border-navy-600 px-3.5 py-2 text-[13px] font-semibold text-white hover:bg-navy-800" @click="router.delete(urls.keepYearly)">Keep yearly</button>
        </div>

        <p v-if="billing.switch" class="text-xs text-on-navy-muted">
            <template v-if="billing.switch.to === 'yearly'">
                Pay a year in advance for your current {{ plural(billing.switch.units, 'unit') }}. Your monthly subscription stops immediately and switches to a yearly subscription.
            </template>
            <template v-else-if="billing.switch.to === 'pending-monthly'">
                Your subscription switches to monthly on {{ billing.switch.switchOn ? `${billing.switch.switchOn},` : '' }} the last day of your paid year. Until then your yearly subscription carries on as it is.
            </template>
            <template v-else>
                Your yearly subscription runs until {{ billing.switch.paidUntil ?? 'the end of the paid year' }}. Monthly billing starts on {{ billing.switch.switchOn ? `${billing.switch.switchOn},` : '' }} the last day of your paid year.
            </template>
        </p>
    </div>
</template>
