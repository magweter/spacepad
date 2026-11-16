<script setup>
// How a workspace pays for Pro: one of three modes, the manual unit price and the billing owner.
import { useForm } from '@inertiajs/vue3';
import Button from '../../../DesignSystem/Button.vue';
import Card from '../../../DesignSystem/Card.vue';
import Notice from '../../../DesignSystem/Notice.vue';

const props = defineProps({ billing: Object, members: Array, hasLiveSubscription: Boolean, url: String });

const modes = [
    { value: 'lemon_squeezy', label: 'Lemon Squeezy', hint: 'Pro through a subscription, or Free without one.' },
    { value: 'manual', label: 'Manually billed', hint: 'Invoiced through our own accounting. MRR is computed from usage at the unit price below.' },
    { value: 'unlimited', label: 'Unlimited', hint: 'Pro for free, never charged and no price shown. For non-profit deals and test accounts.' },
];

// Without an owner the browser would preselect the first member, so the form does the same.
const form = useForm({
    billing_mode: props.billing.mode,
    manual_billing_unit_price: props.billing.unitPrice,
    billing_owner_user_id: props.billing.ownerId ?? props.members[0]?.id ?? null,
});

const submit = () => form.post(props.url, { preserveScroll: true });

const fieldClass = 'block w-full rounded-lg border border-line bg-surface px-3 py-2 text-[13px] text-ink focus:border-navy focus:outline-none';
</script>

<template>
    <Card>
        <h2 class="mb-1 font-display text-base font-bold">Billing</h2>
        <p class="mb-4 text-[13px] text-muted">
            How this workspace pays for Pro. Manually billed and unlimited both give Pro without a Lemon Squeezy subscription.
        </p>

        <form class="flex flex-col gap-5" @submit.prevent="submit">
            <fieldset class="flex flex-col gap-2">
                <legend class="mb-1 text-[13px] font-semibold">Billing</legend>
                <label v-for="m in modes" :key="m.value" class="flex cursor-pointer items-start gap-2.5 rounded-lg border px-3 py-2.5 text-[13px]"
                       :class="form.billing_mode === m.value ? 'border-navy bg-surface-raised' : 'border-line hover:bg-surface-raised'">
                    <input v-model="form.billing_mode" type="radio" name="billing_mode" :value="m.value" class="mt-0.5 accent-navy">
                    <span>
                        <span class="font-semibold">{{ m.label }}</span>
                        <span class="block text-xs text-muted">{{ m.hint }}</span>
                    </span>
                </label>
                <span v-if="form.errors.billing_mode" class="text-xs text-reserved-text">{{ form.errors.billing_mode }}</span>
            </fieldset>

            <Notice v-if="hasLiveSubscription && form.billing_mode !== 'lemon_squeezy'" tone="warning">
                This workspace still has a running Lemon Squeezy subscription. Changing the billing here does not stop it: cancel it in Lemon Squeezy too.
            </Notice>

            <div>
                <label for="manual_billing_unit_price" class="mb-1 block text-[13px] font-semibold">Monthly price per unit</label>
                <div class="relative max-w-xs">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-[13px] text-muted">$</span>
                    <input id="manual_billing_unit_price" v-model="form.manual_billing_unit_price" type="number" step="0.01" min="0"
                           name="manual_billing_unit_price" :placeholder="billing.globalUnitPrice ?? '0.00'" :class="[fieldClass, 'pl-7']">
                </div>
                <p class="mt-1 text-xs text-muted">
                    Only used when manually billed. Leave empty to use the global default ({{ billing.globalUnitPriceFormatted }}
                    from <code>UNIT_PRICE</code>).
                    Effective MRR: {{ billing.effectiveUnitPrice }} &times; {{ billing.billableUnits }} {{ billing.unitsLabel }} = {{ billing.effectiveMrr }}.
                </p>
                <span v-if="form.errors.manual_billing_unit_price" class="mt-1 block text-xs text-reserved-text">{{ form.errors.manual_billing_unit_price }}</span>
            </div>

            <div>
                <label for="billing_owner_user_id" class="mb-1 block text-[13px] font-semibold">Billing owner</label>
                <select id="billing_owner_user_id" v-model="form.billing_owner_user_id" name="billing_owner_user_id" :class="[fieldClass, 'max-w-xs']">
                    <option v-for="m in members" :key="m.id" :value="m.id">{{ m.name }} ({{ m.email }})</option>
                </select>
                <p class="mt-1 text-xs text-muted">Who Lemon Squeezy invoices, and who we contact about it.</p>
                <span v-if="form.errors.billing_owner_user_id" class="mt-1 block text-xs text-reserved-text">{{ form.errors.billing_owner_user_id }}</span>
            </div>

            <div>
                <Button type="submit" :disabled="form.processing">Save billing settings</Button>
            </div>
        </form>
    </Card>
</template>
