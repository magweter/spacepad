<script setup>
// Admin: every user, newest first, 50 to a page, searchable by name or email.
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import PortalShell from '../../Components/PortalShell.vue';
import FlashMessage from '../../Components/FlashMessage.vue';
import Icon from '../../Components/Icon.vue';
import Button from '../../DesignSystem/Button.vue';
import Card from '../../DesignSystem/Card.vue';
import Chip from '../../DesignSystem/Chip.vue';
import AdminTabs from './Partials/AdminTabs.vue';
import UsersPagination from './Partials/UsersPagination.vue';
import UsersImpersonateModal from './Partials/UsersImpersonateModal.vue';

const props = defineProps({
    shell: Object, stats: Object, pendingRoadmap: Number,
    search: String, users: Array, pagination: Object, urls: Object,
});

// The search runs on the server, like before: type and press Enter.
const query = ref(props.search);
function submit() {
    const search = query.value.trim();
    router.get(props.urls.index, search ? { search } : {}, { preserveState: true });
}

const impersonating = ref(null);

const fieldClass = 'rounded-lg border border-line bg-surface px-3 py-2 text-[13px] text-ink focus:border-navy focus:outline-none';
const columns = 'grid-cols-[1.4fr_2fr_1.1fr_0.7fr_0.7fr_0.6fr_1fr_1fr_170px]';
</script>

<template>
    <Head title="Admin" />

    <PortalShell :shell="shell" title="Admin">
        <div class="flex flex-col gap-6 px-6 pt-7 pb-10 md:px-8">
            <AdminTabs active="users" :stats="stats" :pending-roadmap="pendingRoadmap" />
            <FlashMessage />

            <div class="flex flex-col gap-4">
                <form class="flex flex-wrap items-center gap-2.5" role="search" @submit.prevent="submit">
                    <label for="user-search" class="sr-only">Search users</label>
                    <span class="relative flex max-w-[420px] min-w-[220px] flex-[1_1_320px] items-center">
                        <Icon name="search" :size="15" class="pointer-events-none absolute left-3 text-muted" />
                        <input id="user-search" v-model="query" type="search" name="search" placeholder="Search by name or email" :class="[fieldClass, 'w-full pl-9']">
                    </span>
                    <Button type="submit" variant="secondary">Search</Button>
                    <div class="flex-1"></div>
                    <span v-if="pagination.total" class="text-[13px] text-muted">{{ pagination.total }} {{ pagination.total === 1 ? 'user' : 'users' }}</span>
                </form>

                <Card padding="none" class="overflow-x-auto">
                    <div class="min-w-[1100px]">
                        <div class="grid items-center gap-3 border-b border-line px-4 py-2.5 text-[11px] font-semibold tracking-[0.04em] text-muted uppercase" :class="columns">
                            <span>Name</span><span>Email</span><span>Usage type</span><span>Displays</span><span>Boards</span><span>Pro</span><span>Registered</span><span>Last activity</span><span class="text-right">Actions</span>
                        </div>
                        <div v-for="u in users" :key="u.id" class="grid min-h-14 items-center gap-3 border-b border-line-soft px-4 py-2 text-[13px] last:border-b-0 hover:bg-surface-raised" :class="columns">
                            <Link :href="u.urls.show" class="truncate font-semibold text-ink hover:underline">{{ u.name }}</Link>
                            <span class="truncate text-ink-2">{{ u.email }}</span>
                            <span class="truncate text-ink-3">{{ u.usageType ?? '-' }}</span>
                            <span class="tabular-nums">{{ u.displays }}</span>
                            <span class="tabular-nums">{{ u.boards }}</span>
                            <span><Chip :tone="u.pro ? 'navy' : 'neutral'">{{ u.pro ? 'Yes' : 'No' }}</Chip></span>
                            <span class="text-ink-3 tabular-nums">{{ u.registered }}</span>
                            <span class="text-ink-3 tabular-nums">{{ u.lastActivity ?? 'Never' }}</span>
                            <span class="flex items-center justify-end gap-3">
                                <Link :href="u.urls.show" class="text-xs font-semibold text-navy hover:underline">View</Link>
                                <button type="button" class="text-xs font-semibold text-navy hover:underline" @click="impersonating = u">Impersonate</button>
                            </span>
                        </div>
                        <p v-if="!users.length" class="px-4 py-8 text-center text-[13px] text-muted">
                            {{ search ? `No users found matching "${search}"` : 'No users found' }}
                        </p>
                    </div>
                </Card>

                <UsersPagination :pagination="pagination" />
            </div>
        </div>

        <UsersImpersonateModal :user="impersonating" @close="impersonating = null" />
    </PortalShell>
</template>
