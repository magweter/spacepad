<script setup>
// Admin: alerts shown at the top of the portal, such as a calendar provider outage. Active ones
// first, then scheduled, then the last twenty that ended.
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import PortalShell from '../../Components/PortalShell.vue';
import FlashMessage from '../../Components/FlashMessage.vue';
import Icon from '../../Components/Icon.vue';
import Button from '../../DesignSystem/Button.vue';
import Card from '../../DesignSystem/Card.vue';
import Chip from '../../DesignSystem/Chip.vue';
import AdminTabs from './Partials/AdminTabs.vue';

defineProps({ shell: Object, stats: Object, pendingRoadmap: Number, alerts: Object, urls: Object });

const ending = ref(null);
const end = (alert) => {
    ending.value = alert.id;
    router.post(alert.endUrl, {}, { preserveScroll: true, onFinish: () => { ending.value = null; } });
};

const sections = [
    { key: 'active', title: 'Active', empty: 'Nothing is shown in the portal right now.' },
    { key: 'scheduled', title: 'Scheduled', empty: null },
    { key: 'ended', title: 'Ended', empty: null },
];
const providers = { microsoft: 'Microsoft 365', google: 'Google', caldav: 'CalDAV' };
const toneChip = { attention: { tone: 'amber', label: 'Attention' }, action_required: { tone: 'reserved', label: 'Action required' } };
const when = (a) => [`From ${a.startsAt}`, a.endsAt ? `until ${a.endsAt}` : 'until ended'].join(' ');

const headClass = 'border-b border-line px-4 py-2.5 text-left text-[11px] font-semibold tracking-[0.04em] text-muted uppercase';
const smallBtn = 'inline-flex items-center rounded-md border px-2.5 py-1 text-xs font-semibold';
</script>

<template>
    <Head title="Alerts" />

    <PortalShell :shell="shell" title="Admin">
        <template #actions>
            <Button :href="urls.create"><template #icon><Icon name="plus" :size="16" :stroke="2.5" /></template>New alert</Button>
        </template>

        <div class="flex flex-col gap-6 px-6 pt-7 pb-10 md:px-8">
            <AdminTabs active="alerts" :stats="stats" :pending-roadmap="pendingRoadmap" />
            <FlashMessage />

            <p class="max-w-[720px] text-[13px] text-muted">
                An alert shows at the top of every portal page while it runs, for every workspace or only for workspaces with a calendar of one provider.
            </p>

            <p v-if="!alerts.active.length && !alerts.scheduled.length && !alerts.ended.length" class="sp-card px-6 py-8 text-center text-[13px] text-muted">
                No alerts yet. Create one when something outside Spacepad affects your customers.
            </p>

            <template v-else>
                <section v-for="s in sections" v-show="alerts[s.key].length || s.empty" :key="s.key" :aria-labelledby="`${s.key}-heading`" class="flex flex-col gap-3">
                    <h2 :id="`${s.key}-heading`" class="font-display text-base font-bold">{{ s.title }} ({{ alerts[s.key].length }})</h2>
                    <p v-if="!alerts[s.key].length" class="text-[13px] text-muted">{{ s.empty }}</p>
                    <Card v-else padding="none" class="overflow-x-auto">
                        <table class="w-full min-w-[760px] text-[13px]">
                            <thead>
                                <tr>
                                    <th :class="headClass">Alert</th>
                                    <th :class="headClass">Type</th>
                                    <th :class="headClass">For</th>
                                    <th :class="headClass">When</th>
                                    <th :class="[headClass, 'text-right']">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="a in alerts[s.key]" :key="a.id" class="border-b border-line-soft last:border-b-0">
                                    <td class="max-w-[360px] px-4 py-3">
                                        <p class="font-semibold">{{ a.title }}</p>
                                        <p v-if="a.body" class="mt-0.5 truncate text-xs text-muted">{{ a.body }}</p>
                                    </td>
                                    <td class="px-4 py-3"><Chip :tone="toneChip[a.tone]?.tone ?? 'neutral'">{{ toneChip[a.tone]?.label ?? a.tone }}</Chip></td>
                                    <td class="px-4 py-3 whitespace-nowrap text-ink-3">{{ a.provider ? `${providers[a.provider]} workspaces` : 'All workspaces' }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap text-ink-3 tabular-nums">{{ when(a) }}</td>
                                    <td class="px-4 py-3">
                                        <span class="flex items-center justify-end gap-2">
                                            <Link :href="a.editUrl" :class="[smallBtn, 'border-line bg-surface text-ink hover:bg-surface-raised']">Edit</Link>
                                            <button v-if="a.status !== 'ended'" type="button" :disabled="ending === a.id"
                                                    :class="[smallBtn, 'border-reserved-border bg-surface text-reserved-text hover:bg-reserved-bg disabled:opacity-50']"
                                                    @click="end(a)">{{ a.status === 'scheduled' ? 'Cancel' : 'End now' }}</button>
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </Card>
                </section>
            </template>
        </div>
    </PortalShell>
</template>
