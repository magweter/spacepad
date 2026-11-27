<script setup>
// Admin: one user. A drill-down from the users list, so it has the way back up instead of the tabs.
import { ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import PortalShell from '../../Components/PortalShell.vue';
import Icon from '../../Components/Icon.vue';
import Button from '../../DesignSystem/Button.vue';
import Card from '../../DesignSystem/Card.vue';
import PlanChip from './Partials/PlanChip.vue';
import Chip from '../../DesignSystem/Chip.vue';
import Notice from '../../DesignSystem/Notice.vue';
import StatusBadge from '../../DesignSystem/StatusBadge.vue';
import UsersImpersonateModal from './Partials/UsersImpersonateModal.vue';
import UserDeleteCard from './Partials/UserDeleteCard.vue';

const props = defineProps({
    shell: Object, user: Object, counts: Object, subscription: Object, billingChanges: Array, billingWorkspace: Object, urls: Object,
});

const account = [
    { label: 'Email', value: props.user.email },
    { label: 'Name', value: props.user.name },
    { label: 'User ID', value: props.user.id, mono: true },
    { label: 'Usage type', value: props.user.usageType ?? 'Not set' },
    { label: 'Created', value: props.user.created },
    { label: 'Last activity', value: props.user.lastActivity ?? 'Never' },
];
const data = [
    { label: 'Outlook accounts', value: props.counts.outlookAccounts },
    { label: 'Google accounts', value: props.counts.googleAccounts },
    { label: 'CalDAV accounts', value: props.counts.caldavAccounts },
    { label: 'Displays', value: props.counts.displays },
    { label: 'Devices', value: props.counts.devices },
    { label: 'Workspaces', value: props.counts.workspaces },
];

// Subscription states as status colours, always with the word next to them.
const statusTone = (s) => ({ active: 'available', on_trial: 'available', manual: 'available', past_due: 'checkin', paused: 'checkin', unpaid: 'reserved' }[s] ?? 'offline');

const impersonating = ref(null);

const heading = 'font-display text-base font-bold tracking-tight';
</script>

<template>
    <Head :title="`User details - ${user.email}`" />

    <PortalShell :shell="shell" title="User details" :subtitle="user.email">
        <template #actions>
            <Button variant="secondary" :href="urls.back"><template #icon><Icon name="arrowLeft" :size="16" /></template>Back to Admin</Button>
            <Button v-if="!user.isSelf" @click="impersonating = { email: user.email, urls: { impersonate: urls.impersonate } }">Impersonate</Button>
        </template>

        <div class="flex max-w-[1040px] flex-col gap-4 px-6 pt-6 pb-10 md:px-10">


            <Card>
                <h2 :class="heading">Account information</h2>
                <dl class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div v-for="f in account" :key="f.label">
                        <dt class="text-xs font-medium text-muted">{{ f.label }}</dt>
                        <dd class="mt-1 text-[13px] text-ink" :class="f.mono ? 'font-mono' : ''">{{ f.value }}</dd>
                    </div>
                </dl>
            </Card>

            <Card v-if="subscription">
                <h2 :class="heading">Subscription information</h2>
                <dl class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs font-medium text-muted">Plan</dt>
                        <dd class="mt-1"><PlanChip :plan="subscription.plan" /></dd>
                    </div>
                    <template v-if="subscription.info">
                        <div>
                            <dt class="text-xs font-medium text-muted">Status</dt>
                            <dd class="mt-1"><StatusBadge :status="statusTone(subscription.info.status)">{{ subscription.info.statusLabel }}</StatusBadge></dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-muted">Monthly price</dt>
                            <dd class="mt-1 text-[13px] tabular-nums">${{ subscription.info.price }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-muted">MRR</dt>
                            <dd class="mt-1 text-[13px] tabular-nums">${{ subscription.info.mrr }}</dd>
                        </div>
                        <div v-if="subscription.info.endsAt">
                            <dt class="text-xs font-medium text-muted">Subscription ends</dt>
                            <dd class="mt-1 text-[13px] tabular-nums">{{ subscription.info.endsAt }}</dd>
                        </div>
                    </template>
                </dl>
            </Card>

            <Card v-if="billingChanges.length" padding="none" class="overflow-x-auto">
                <div class="px-5 pt-5 pb-3">
                    <h2 :class="heading">Billing changes</h2>
                    <p class="mt-1 text-[13px] text-muted">History of license-count changes (displays + boards &times; 2) detected during analytics refreshes.</p>
                </div>
                <div class="min-w-[720px]">
                    <div class="grid grid-cols-[1.3fr_0.8fr_1fr_1fr_1fr_1.4fr] gap-3 border-y border-line px-5 py-2.5 text-[11px] font-semibold tracking-[0.04em] text-muted uppercase">
                        <span>Date</span><span>Change</span><span>Licenses</span><span>Displays</span><span>Boards</span><span>MRR</span>
                    </div>
                    <div v-for="c in billingChanges" :key="c.id" class="grid grid-cols-[1.3fr_0.8fr_1fr_1fr_1fr_1.4fr] items-center gap-3 border-b border-line-soft px-5 py-2.5 text-[13px] tabular-nums last:border-b-0">
                        <span>{{ c.detectedAt }}</span>
                        <span>
                            <Chip v-if="c.increase" tone="available">&uarr; +{{ c.delta }}</Chip>
                            <Chip v-else tone="reserved">&darr; {{ c.delta }}</Chip>
                        </span>
                        <span>{{ c.licenses[0] }} &rarr; {{ c.licenses[1] }}</span>
                        <span class="text-ink-3">{{ c.displays[0] }} &rarr; {{ c.displays[1] }}</span>
                        <span class="text-ink-3">{{ c.boards[0] }} &rarr; {{ c.boards[1] }}</span>
                        <span>${{ c.mrr[0] }} &rarr; ${{ c.mrr[1] }}</span>
                    </div>
                </div>
            </Card>

            <Card>
                <h2 :class="heading">Billing</h2>
                <template v-if="billingWorkspace">
                    <p class="mt-2 text-[13px] text-ink-2">
                        Billing belongs to a workspace, not a person. This user is billed under
                        <strong>{{ billingWorkspace.name }}</strong>, currently
                        {{ billingWorkspace.units }} {{ billingWorkspace.unitsLabel }}
                        ({{ billingWorkspace.displays }} display(s) &times; 1, {{ billingWorkspace.boards }} board(s) &times; 2).
                    </p>
                    <div class="mt-4"><Button :href="billingWorkspace.url" external>Manage workspace billing</Button></div>
                </template>
                <p v-else class="mt-2 text-[13px] text-muted">This user has no workspace, so there is nothing to bill.</p>
            </Card>

            <Card>
                <h2 :class="heading">Data summary</h2>
                <dl class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div v-for="f in data" :key="f.label">
                        <dt class="text-xs font-medium text-muted">{{ f.label }}</dt>
                        <dd class="mt-1 text-[13px] tabular-nums">{{ f.value }}</dd>
                    </div>
                </dl>
            </Card>

            <UserDeleteCard v-if="!user.isSelf" :email="user.email" :url="urls.delete" />
            <Notice v-else tone="warning" title="You cannot delete your own account">
                Please ask another admin to perform this action if needed.
            </Notice>
        </div>

        <UsersImpersonateModal :user="impersonating" @close="impersonating = null" />
    </PortalShell>
</template>
