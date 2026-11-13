<script setup>
// Every workspace with what it uses and what it pays. Searched on the server, 50 per page.
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import PortalShell from '../../Components/PortalShell.vue';
import FlashMessage from '../../Components/FlashMessage.vue';
import Icon from '../../Components/Icon.vue';
import Card from '../../DesignSystem/Card.vue';
import StatusBadge from '../../DesignSystem/StatusBadge.vue';
import AdminTabs from './Partials/AdminTabs.vue';
import WorkspacePagination from './Partials/WorkspacePagination.vue';

const props = defineProps({
    shell: Object, stats: Object, pendingRoadmap: Number,
    workspaces: Object, search: String, urls: Object,
});

const query = ref(props.search ?? '');
const submit = () => router.get(props.urls.index, query.value ? { search: query.value } : {}, { preserveState: true });

const columns = 'grid-cols-[2fr_2fr_0.8fr_0.8fr_0.8fr_0.7fr_1.3fr_0.9fr_1fr_1fr]';
const fieldClass = 'rounded-lg border border-line bg-surface px-3 py-2 text-[13px] text-ink focus:border-navy focus:outline-none';
</script>

<template>
    <Head title="Workspaces" />

    <PortalShell :shell="shell" title="Admin">
        <div class="flex flex-col gap-6 px-6 pt-7 pb-10 md:px-8">
            <AdminTabs active="workspaces" :stats="stats" :pending-roadmap="pendingRoadmap" />
            <FlashMessage />

            <form role="search" class="flex" @submit.prevent="submit">
                <label for="workspace-search" class="sr-only">Search workspaces</label>
                <span class="relative flex w-full max-w-[520px] items-center">
                    <Icon name="search" :size="15" class="pointer-events-none absolute left-3 text-muted" />
                    <input id="workspace-search" v-model="query" type="search" name="search"
                           placeholder="Search by workspace name, or by a member's name or email..." :class="[fieldClass, 'w-full pl-9']">
                </span>
            </form>

            <Card padding="none" class="overflow-x-auto">
                <div class="min-w-[1080px]">
                    <div class="grid items-center gap-3 border-b border-line px-4 py-2.5 text-[11px] font-semibold tracking-[0.04em] text-muted uppercase" :class="columns">
                        <span>Workspace</span><span>Billing owner</span><span>Members</span><span>Displays</span><span>Boards</span>
                        <span>Units</span><span>Plan</span><span>MRR</span><span>Created</span><span>Actions</span>
                    </div>
                    <div v-for="w in workspaces.data" :key="w.id"
                         class="grid min-h-14 items-center gap-3 border-b border-line-soft px-4 py-2 text-[13px] last:border-b-0 hover:bg-surface-raised" :class="columns">
                        <Link :href="w.url" class="truncate font-semibold text-ink hover:underline">{{ w.name }}</Link>
                        <span class="truncate text-ink-3">{{ w.owner?.email ?? '-' }}</span>
                        <span class="tabular-nums text-ink-3">{{ w.members }}</span>
                        <span class="tabular-nums text-ink-3">{{ w.displays }}</span>
                        <span class="tabular-nums text-ink-3">{{ w.boards }}</span>
                        <span class="tabular-nums text-ink-3">{{ w.units }}</span>
                        <span><StatusBadge :status="w.plan.tone">{{ w.plan.label }}</StatusBadge></span>
                        <span class="tabular-nums text-ink-3">{{ w.mrr ?? '-' }}</span>
                        <span class="tabular-nums text-ink-3">{{ w.created ?? '-' }}</span>
                        <span class="flex items-center gap-3">
                            <Link :href="w.url" class="font-semibold text-navy hover:underline">View</Link>
                            <a v-if="w.owner" :href="w.owner.url" class="font-semibold text-ink-3 hover:text-ink hover:underline">Owner</a>
                        </span>
                    </div>
                    <p v-if="!workspaces.data.length" class="px-4 py-8 text-center text-[13px] text-muted">
                        <template v-if="search">No workspaces found matching "{{ search }}"</template>
                        <template v-else>No workspaces found</template>
                    </p>
                </div>
            </Card>

            <WorkspacePagination :paginator="workspaces" />
        </div>
    </PortalShell>
</template>
