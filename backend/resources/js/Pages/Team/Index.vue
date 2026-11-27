<script setup>
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import PortalShell from '../../Components/PortalShell.vue';
import { openUpgrade } from '../../Components/Upgrade/upgrade';
import Notice from '../../DesignSystem/Notice.vue';
import InviteCard from './Partials/InviteCard.vue';
import MembersTable from './Partials/MembersTable.vue';
import Invitations from './Partials/Invitations.vue';
import JoinRequests from './Partials/JoinRequests.vue';
import RolesExplainer from './Partials/RolesExplainer.vue';

const props = defineProps({
    shell: Object, members: Array, invitations: Array, joinRequests: Array, invitationLifetimeDays: Number,
    can: Object, needsPro: Boolean, roles: Array, urls: Object,
});

const managers = computed(() => props.members.filter((m) => m.role !== 'member').length);
const figures = computed(() => [
    { label: 'Members', value: props.members.length },
    { label: 'Owners and admins', value: managers.value },
    ...(props.can.revokeInvitation ? [{ label: 'Pending invitations', value: props.invitations.length }] : []),
    ...(props.joinRequests.length ? [{ label: 'Join requests', value: props.joinRequests.length }] : []),
]);
</script>

<template>
    <Head title="Team" />

    <PortalShell :shell="shell" title="Team">
        <div class="flex max-w-[1040px] flex-col gap-4 px-6 pt-6 pb-10 md:px-10">


            <div class="sp-kpistrip">
                <div v-for="f in figures" :key="f.label" class="sp-kpistrip-item">
                    <span class="sp-kpistrip-label">{{ f.label }}</span>
                    <span class="sp-kpistrip-value">{{ f.value }}</span>
                </div>
            </div>

            <JoinRequests v-if="joinRequests.length" :requests="joinRequests" />

            <InviteCard v-if="can.invite" :url="urls.invite" />
            <Notice v-else-if="needsPro" tone="info" title="Inviting colleagues is part of Pro"
                    action="See what Pro adds" @action="openUpgrade('team')">
                {{ can.manageBilling ? 'Upgrade to Pro to invite colleagues into this workspace.' : 'Ask an owner or admin of this workspace to upgrade to Pro.' }}
            </Notice>

            <MembersTable :members="members" :roles="roles" :can="can" />

            <Invitations v-if="can.revokeInvitation" :invitations="invitations" :lifetime-days="invitationLifetimeDays" :can-resend="can.invite" />

            <RolesExplainer />
        </div>
    </PortalShell>
</template>
