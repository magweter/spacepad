<script setup>
// The public roadmap and the suggestions users sent in that wait for approval.
import { computed, ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import PortalShell from '../../Components/PortalShell.vue';
import Icon from '../../Components/Icon.vue';
import Button from '../../DesignSystem/Button.vue';
import Card from '../../DesignSystem/Card.vue';
import Notice from '../../DesignSystem/Notice.vue';
import AdminTabs from './Partials/AdminTabs.vue';
import RoadmapDeleteModal from './Partials/RoadmapDeleteModal.vue';
import RoadmapStatusChip from './Partials/RoadmapStatusChip.vue';

defineProps({
    shell: Object, stats: Object, pendingRoadmap: Number,
    pending: Array, approved: Array, urls: Object,
});

const page = usePage();
const errors = computed(() => Object.values(page.props.errors || {}));

const busy = ref(false);
const approve = (item) => router.post(item.approveUrl, {}, {
    preserveScroll: true,
    onStart: () => { busy.value = true; },
    onFinish: () => { busy.value = false; },
});

// Deleting asks first, in a modal. `kind` words the question for a suggestion or an item.
const deleting = ref(null);

const headClass = 'border-b border-line px-4 py-2.5 text-left text-[11px] font-semibold tracking-[0.04em] text-muted uppercase';
const smallBtn = 'inline-flex items-center rounded-md border px-2.5 py-1 text-xs font-semibold';
</script>

<template>
    <Head title="Roadmap" />

    <PortalShell :shell="shell" title="Admin">
        <div class="flex flex-col gap-6 px-6 pt-7 pb-10 md:px-8">
            <AdminTabs active="roadmap" :stats="stats" :pending-roadmap="pendingRoadmap" />
            <div class="flex flex-wrap items-center justify-between gap-3">
                <p class="max-w-[720px] text-[13px] text-muted">Suggestions from customers and the items on the public roadmap.</p>
                <Button :href="urls.create"><template #icon><Icon name="plus" :size="16" :stroke="2.5" /></template>New item</Button>
            </div>
            <Notice v-if="errors.length" tone="danger" title="Something went wrong">
                <template v-for="(e, i) in errors" :key="i">{{ e }}<br v-if="i < errors.length - 1"></template>
            </Notice>

            <section v-if="pending.length" aria-labelledby="pending-heading" class="flex flex-col gap-3">
                <h2 id="pending-heading" class="font-display text-base font-bold">Pending suggestions ({{ pending.length }})</h2>
                <Card padding="none" class="overflow-x-auto">
                    <table class="min-w-[720px] w-full text-[13px]">
                        <thead class="bg-checkin-bg">
                            <tr>
                                <th :class="headClass">Title</th>
                                <th :class="headClass">Submitted by</th>
                                <th :class="headClass">Date</th>
                                <th :class="[headClass, 'text-right']">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="item in pending" :key="item.id" class="border-b border-line-soft last:border-b-0">
                                <td class="px-4 py-3">
                                    <p class="font-semibold">{{ item.title }}</p>
                                    <p v-if="item.description" class="mt-0.5 text-xs text-muted">{{ item.description }}</p>
                                </td>
                                <td class="px-4 py-3 text-ink-3">
                                    {{ item.submittedBy?.name ?? '-' }}
                                    <span v-if="item.submittedBy?.email" class="block text-xs text-muted">{{ item.submittedBy.email }}</span>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-ink-3">{{ item.date }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-end gap-2">
                                        <button type="button" :disabled="busy" :class="[smallBtn, 'border-available bg-available text-white hover:opacity-90 disabled:opacity-50']" @click="approve(item)">Approve</button>
                                        <Link :href="item.editUrl" :class="[smallBtn, 'border-line bg-surface text-ink hover:bg-surface-raised']">Edit</Link>
                                        <button type="button" :class="[smallBtn, 'border-reserved-border bg-surface text-reserved-text hover:bg-reserved-bg']" @click="deleting = { ...item, kind: 'suggestion' }">Delete</button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </Card>
            </section>

            <section aria-labelledby="public-heading" class="flex flex-col gap-3">
                <h2 id="public-heading" class="font-display text-base font-bold">Public roadmap ({{ approved.length }} items)</h2>
                <Card padding="none" class="overflow-x-auto">
                    <table class="min-w-[720px] w-full text-[13px]">
                        <thead>
                            <tr>
                                <th :class="headClass">Title</th>
                                <th :class="headClass">Status</th>
                                <th :class="headClass">Expected</th>
                                <th :class="headClass">Votes</th>
                                <th :class="[headClass, 'text-right']">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="item in approved" :key="item.id" class="border-b border-line-soft last:border-b-0">
                                <td class="px-4 py-3">
                                    <p class="font-semibold">{{ item.title }}</p>
                                    <p v-if="item.description" class="mt-0.5 text-xs text-muted">{{ item.description }}</p>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap"><RoadmapStatusChip :status="item.status" /></td>
                                <td class="px-4 py-3 whitespace-nowrap text-ink-3">{{ item.expected ?? '-' }}</td>
                                <td class="px-4 py-3 font-semibold tabular-nums text-ink-2">{{ item.votes }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-end gap-2">
                                        <Link :href="item.editUrl" :class="[smallBtn, 'border-line bg-surface text-ink hover:bg-surface-raised']">Edit</Link>
                                        <button type="button" :class="[smallBtn, 'border-reserved-border bg-surface text-reserved-text hover:bg-reserved-bg']" @click="deleting = { ...item, kind: 'item' }">Delete</button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!approved.length">
                                <td colspan="5" class="px-4 py-10 text-center text-muted">
                                    No roadmap items yet. <Link :href="urls.create" class="font-semibold text-navy hover:underline">Create one</Link>.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </Card>
            </section>
        </div>

        <RoadmapDeleteModal :item="deleting" @close="deleting = null" />
    </PortalShell>
</template>
