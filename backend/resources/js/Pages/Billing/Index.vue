<script setup>
import { computed, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import PortalShell from '../../Components/PortalShell.vue';
import FlashMessage from '../../Components/FlashMessage.vue';
import PlanCard from './Partials/PlanCard.vue';
import UsageCard from './Partials/UsageCard.vue';
import SubscriptionCard from './Partials/SubscriptionCard.vue';
import LicenseCard from './Partials/LicenseCard.vue';
import CancelModal from './Partials/CancelModal.vue';
import ManageModal from './Partials/ManageModal.vue';

const props = defineProps({
    shell: Object, billing: Object, license: Object, cancellationReasons: Array, urls: Object, errors: Object,
});

// The cancel modal opens again by itself when its form came back with an error.
const cancelling = ref(Boolean(props.errors?.reason || props.errors?.details));
const managing = ref(false);

const subtitle = computed(() => {
    const plan = props.billing.hasPro ? (props.billing.selfHosted ? 'Self-hosted Pro' : 'Pro') : 'Free plan';
    return [plan, props.billing.interval?.toLowerCase()].filter(Boolean).join(', ') + '. Per display, never per user.';
});
</script>

<template>
    <Head title="Billing" />

    <PortalShell :shell="shell" title="Billing">
        <div class="flex flex-col gap-6 px-6 pt-7 pb-10 md:px-8">
            <p class="-mt-2 text-[13px] text-muted">{{ subtitle }}</p>

            <FlashMessage />

            <div class="flex flex-wrap items-start gap-6">
                <section aria-label="Plan" class="flex max-w-full min-w-0 flex-[1_1_380px] flex-col gap-4 lg:max-w-[480px]">
                    <PlanCard :billing="billing" :urls="urls" @manage="managing = true" />
                    <UsageCard :billing="billing" />
                </section>

                <section aria-label="Subscription" class="flex min-w-0 flex-[999_1_460px] flex-col gap-4">
                    <SubscriptionCard :billing="billing" @cancel="cancelling = true" />
                    <LicenseCard v-if="license" :license="license" :can-manage="billing.canManage" :url="urls.license" :errors="errors" />
                </section>
            </div>
        </div>

        <CancelModal v-if="billing.cancel" :open="cancelling" :ends-on="billing.cancel.endsOn" :reasons="cancellationReasons" :url="urls.cancel" @close="cancelling = false" />
        <ManageModal :open="managing" @close="managing = false" />
    </PortalShell>
</template>
