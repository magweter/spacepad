<script setup>
// One workspace: what it uses, what it pays and who is in it. A drill-down from the workspace
// list, so it offers the way back up instead of the admin tabs.
import PlanChip from './Partials/PlanChip.vue';
import { Head, Link } from '@inertiajs/vue3';
import PortalShell from '../../Components/PortalShell.vue';
import Icon from '../../Components/Icon.vue';
import Card from '../../DesignSystem/Card.vue';
import WorkspaceBillingForm from './Partials/WorkspaceBillingForm.vue';

defineProps({
    shell: Object, stats: Object, pendingRoadmap: Number,
    workspace: Object, usage: Object, subscription: Object, billing: Object,
    billingChanges: Array, members: Array, urls: Object,
});

const termClass = 'text-[13px] font-medium text-muted';
</script>

<template>
    <Head :title="`Workspace - ${workspace.name}`" />

    <PortalShell :shell="shell" :title="workspace.name" subtitle="Usage, billing and members for this workspace">
        <template #actions>
            <Link :href="urls.index" class="inline-flex items-center gap-1.5 text-[13px] font-semibold text-navy hover:underline">
                <Icon name="arrowLeft" :size="15" />Back to Workspaces
            </Link>
        </template>

        <div class="flex max-w-4xl flex-col gap-6 px-6 pt-6 pb-10 md:px-10">


            <Card>
                <h2 class="mb-4 font-display text-base font-bold">Workspace</h2>
                <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <dt :class="termClass">Workspace ID</dt>
                        <dd class="mt-1 font-mono text-[13px]">{{ workspace.id }}</dd>
                    </div>
                    <div>
                        <dt :class="termClass">Created</dt>
                        <dd class="mt-1">{{ workspace.created }}</dd>
                    </div>
                    <div>
                        <dt :class="termClass">Billing owner</dt>
                        <dd class="mt-1">
                            <a v-if="workspace.owner" :href="workspace.owner.url" class="font-semibold text-navy hover:underline">
                                {{ workspace.owner.name }} ({{ workspace.owner.email }})
                            </a>
                            <span v-else class="text-muted">Nobody</span>
                        </dd>
                    </div>
                    <div>
                        <dt :class="termClass">Plan</dt>
                        <dd class="mt-1"><PlanChip :plan="workspace.plan" /></dd>
                    </div>
                </dl>
            </Card>

            <Card>
                <h2 class="mb-4 font-display text-base font-bold">Billable usage</h2>
                <dl class="mb-4 grid grid-cols-3 gap-4">
                    <div>
                        <dt :class="termClass">Displays</dt>
                        <dd class="mt-1 font-display text-[26px] leading-none font-bold tabular-nums">{{ usage.displays }}</dd>
                    </div>
                    <div>
                        <dt :class="termClass">Boards</dt>
                        <dd class="mt-1 font-display text-[26px] leading-none font-bold tabular-nums">{{ usage.boards }}</dd>
                    </div>
                    <div>
                        <dt :class="termClass">Total units</dt>
                        <dd class="mt-1 font-display text-[26px] leading-none font-bold text-navy tabular-nums">{{ usage.total }}</dd>
                    </div>
                </dl>
                <p class="text-xs text-muted">
                    {{ usage.displays }} display(s) &times; 1, plus {{ usage.boards }} board(s) &times; 2 = {{ usage.total }} unit(s).
                    Read from the workspace's usage counters, which are the same figures the subscription is sized on.
                </p>
            </Card>

            <Card v-if="subscription">
                <h2 class="mb-4 font-display text-base font-bold">Subscription</h2>
                <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <dt :class="termClass">Status</dt>
                        <dd class="mt-1">{{ subscription.status }}</dd>
                    </div>
                    <div>
                        <dt :class="termClass">MRR</dt>
                        <dd class="mt-1 tabular-nums">{{ subscription.mrr }}</dd>
                    </div>
                    <div>
                        <dt :class="termClass">Billing interval</dt>
                        <dd class="mt-1">{{ subscription.interval ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt :class="termClass">Subscription ends</dt>
                        <dd class="mt-1">{{ subscription.endsAt ?? '-' }}</dd>
                    </div>
                </dl>
            </Card>

            <WorkspaceBillingForm :billing="billing" :members="members" :has-live-subscription="workspace.hasLiveSubscription" :url="urls.billing" />

            <Card v-if="billingChanges.length" padding="none" class="overflow-x-auto">
                <h2 class="px-5 pt-5 pb-3 font-display text-base font-bold">Billing changes</h2>
                <table class="min-w-full text-[13px]">
                    <thead>
                        <tr class="border-b border-line text-left text-[11px] font-semibold tracking-[0.04em] text-muted uppercase">
                            <th class="py-2.5 pr-3 pl-5">When</th>
                            <th class="px-3 py-2.5">Change</th>
                            <th class="px-3 py-2.5">Units</th>
                            <th class="px-3 py-2.5">Displays</th>
                            <th class="px-3 py-2.5">Boards</th>
                            <th class="py-2.5 pr-5 pl-3 text-right">MRR</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="c in billingChanges" :key="c.id" class="border-b border-line-soft last:border-b-0">
                            <td class="py-2.5 pr-3 pl-5 text-muted">{{ c.when }}</td>
                            <td class="px-3 py-2.5">
                                <span class="sp-badge" :class="c.increase ? 'sp-badge-available' : 'sp-badge-reserved'">
                                    {{ c.increase ? 'Up' : 'Down' }} {{ c.delta }}
                                </span>
                            </td>
                            <td class="px-3 py-2.5 tabular-nums text-ink-2">{{ c.units[0] }} &rarr; {{ c.units[1] }}</td>
                            <td class="px-3 py-2.5 tabular-nums text-ink-2">{{ c.displays[0] }} &rarr; {{ c.displays[1] }}</td>
                            <td class="px-3 py-2.5 tabular-nums text-ink-2">{{ c.boards[0] }} &rarr; {{ c.boards[1] }}</td>
                            <td class="py-2.5 pr-5 pl-3 text-right tabular-nums text-ink-2">
                                <span v-if="c.mrr === null" class="text-muted" title="Waiting for the next MRR refresh">-</span>
                                <template v-else>{{ c.mrr }}</template>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </Card>

            <Card>
                <h2 class="mb-3 font-display text-base font-bold">Members ({{ members.length }})</h2>
                <ul class="divide-y divide-line-soft">
                    <li v-for="m in members" :key="m.id" class="flex items-center justify-between gap-3 py-2 text-[13px]">
                        <a :href="m.url" class="truncate font-semibold text-navy hover:underline">{{ m.name }} ({{ m.email }})</a>
                        <span class="text-xs text-muted">{{ m.role }}</span>
                    </li>
                </ul>
            </Card>
        </div>
    </PortalShell>
</template>
