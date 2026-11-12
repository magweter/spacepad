<script setup>
// The one place to look for what this workspace can do about its subscription: a short note that
// matches the state, who to ask when you cannot pay yourself, and cancelling with a reason.
import { computed } from 'vue';

const props = defineProps({ billing: Object });
defineEmits(['cancel']);

const notes = {
    manual: null, // has a mail link, rendered below
    unlimited: 'This workspace has Pro at no charge.',
    portal: 'Change your payment method, download invoices or manage your subscription in the Lemon Squeezy billing portal.',
    modal: 'Your subscription is managed in Lemon Squeezy.',
    checkout: 'Pro unlocks multiple displays, boards, check-in, personalisation and inviting colleagues.',
    others: 'This workspace is on Pro. Only owners and admins can change the subscription.',
    ask: 'Ask an owner or admin of this workspace to upgrade to Pro.',
};
const note = computed(() => notes[props.billing.state] ?? null);
const showManagers = computed(() => ['others', 'ask'].includes(props.billing.state) && props.billing.managers.length);
</script>

<template>
    <div class="sp-card flex flex-col gap-3 px-[18px] py-4">
        <div class="flex items-center justify-between gap-3">
            <h2 class="text-[13px] font-semibold tracking-wide text-muted uppercase">Subscription</h2>
            <span v-if="billing.interval" class="sp-chip sp-chip-neutral">{{ billing.interval }}</span>
        </div>

        <p v-if="billing.state === 'manual'" class="text-[13px] text-ink-2">
            We invoice this workspace directly at the agreed price, so there is no subscription to manage here. For anything about your invoice, email
            <a href="mailto:support@spacepad.io" class="font-semibold text-navy hover:underline">support@spacepad.io</a>.
        </p>
        <p v-else-if="note" class="text-[13px] text-ink-2">{{ note }}</p>
        <p v-else class="text-[13px] text-ink-2">Pro on this instance comes from a self-hosted license.</p>

        <ul v-if="showManagers" class="flex flex-col gap-2 border-t border-line-soft pt-3 text-[13px]">
            <li v-for="m in billing.managers" :key="m.email" class="flex flex-col">
                <span class="font-semibold">{{ m.name }}</span>
                <a :href="`mailto:${m.email}`" class="text-xs text-muted hover:underline">{{ m.email }}</a>
            </li>
        </ul>

        <div v-if="billing.cancel" class="flex justify-end border-t border-line-soft pt-3">
            <button type="button" class="text-[13px] font-semibold text-muted hover:text-reserved-text" @click="$emit('cancel')">Cancel subscription</button>
        </div>
        <p v-else-if="billing.cancelledUntil" class="border-t border-line-soft pt-3 text-[13px] text-muted">
            Your subscription is cancelled and ends on {{ billing.cancelledUntil }}.
        </p>
    </div>
</template>
