<script setup>
// Everyone in the workspace. Only owners change roles or remove members; everything a removed
// member made stays in the workspace.
import { router } from '@inertiajs/vue3';
import Chip from '../../../DesignSystem/Chip.vue';

defineProps({ members: Array, roles: Array, can: Object });

const initials = (name) => (name || '?').split(/\s+/).map((p) => p[0]).slice(0, 2).join('').toUpperCase();

function changeRole(member, role) {
    router.patch(member.urls.update, { role }, { preserveScroll: true });
}

function remove(member) {
    if (confirm(`Remove ${member.email} from this workspace? Their displays and boards stay in the workspace.`)) {
        router.delete(member.urls.destroy, { preserveScroll: true });
    }
}
</script>

<template>
    <div class="sp-card overflow-x-auto p-0">
        <div class="min-w-[560px]">
            <div class="grid grid-cols-[2.2fr_1.3fr_1.2fr_80px] gap-3 border-b border-line px-[18px] py-2.5 text-[11px] font-semibold tracking-wide text-muted uppercase">
                <span>Member</span><span>Role</span><span>Member since</span><span class="sr-only">Actions</span>
            </div>
            <div v-for="m in members" :key="m.id" class="grid grid-cols-[2.2fr_1.3fr_1.2fr_80px] items-center gap-3 border-b border-line-soft px-[18px] py-3 text-[13px] last:border-b-0">
                <span class="flex min-w-0 items-center gap-3">
                    <span class="flex h-[34px] w-[34px] flex-none items-center justify-center rounded-full text-xs font-semibold"
                          :class="m.isSelf ? 'bg-navy text-white' : 'bg-navy-50 text-navy'">{{ initials(m.name) }}</span>
                    <span class="flex min-w-0 flex-col">
                        <span class="truncate font-semibold">{{ m.name }} <span v-if="m.isSelf" class="font-medium text-muted">(you)</span></span>
                        <span class="truncate text-xs text-muted">{{ m.email }}</span>
                    </span>
                </span>
                <span>
                    <select v-if="can.changeRoles && !m.isSelf" :value="m.role" :aria-label="`Role for ${m.name}`"
                            class="rounded-lg border border-line bg-surface px-2.5 py-1.5 text-xs focus:border-navy focus:outline-none"
                            @change="changeRole(m, $event.target.value)">
                        <option v-for="r in roles" :key="r.value" :value="r.value">{{ r.label }}</option>
                    </select>
                    <Chip v-else :tone="m.role === 'owner' ? 'amber' : m.role === 'admin' ? 'navy' : 'neutral'">{{ m.roleLabel }}</Chip>
                </span>
                <span class="text-muted">{{ m.since ?? '-' }}</span>
                <span class="text-right">
                    <button v-if="can.removeMembers && !m.isSelf" type="button" class="text-xs font-semibold text-reserved-text hover:underline" @click="remove(m)">Remove</button>
                </span>
            </div>
        </div>
    </div>
</template>
