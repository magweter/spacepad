<script setup>
import { computed, ref, watch } from 'vue';
import { Head } from '@inertiajs/vue3';
import PortalShell from '../../Components/PortalShell.vue';
import FlashMessage from '../../Components/FlashMessage.vue';
import Icon from '../../Components/Icon.vue';
import Button from '../../DesignSystem/Button.vue';
import Card from '../../DesignSystem/Card.vue';
import EmptyState from '../../DesignSystem/EmptyState.vue';
import Notice from '../../DesignSystem/Notice.vue';
import SourceCard from './Partials/SourceCard.vue';
import RoomCalendars from './Partials/RoomCalendars.vue';
import ConnectModal from './Partials/ConnectModal.vue';
import BookingMethodModal from './Partials/BookingMethodModal.vue';
import ServiceAccountModal from './Partials/ServiceAccountModal.vue';
import AdminConsentModal from './Partials/AdminConsentModal.vue';

const props = defineProps({
    shell: Object, accounts: Array, calendars: Array, notices: Array,
    openModal: Object, adminConsent: Object, urls: Object,
});

// Which modal is open: { type, accountId } or { type: 'connect', provider, permission }.
const modal = ref(null);
const close = () => { modal.value = null; };
const account = computed(() => props.accounts.find((a) => String(a.id) === String(modal.value?.accountId)) ?? null);

const open = (type, accountId = null) => { modal.value = { type, accountId }; };
const connect = (provider = null, permission = 'write') => { modal.value = { type: 'connect', provider, permission }; };

// A flow that just ended (or a link from the overview) asks for a modal; every new answer from
// the server can ask again, so this follows the props rather than reading them once.
watch(() => props.openModal, (value) => { if (value) open(value.type, value.accountId); }, { immediate: true });
watch(() => props.adminConsent, (value) => { if (value) modal.value = { type: 'admin-consent' }; }, { immediate: true });

</script>

<template>
    <Head title="Calendars" />

    <PortalShell :shell="shell" title="Calendars">
        <template #actions>
            <Button @click="connect()"><template #icon><Icon name="plus" :size="16" :stroke="2.5" /></template>Connect source</Button>
        </template>

        <div class="flex flex-col gap-6 px-6 pt-7 pb-10 md:px-8">
            <FlashMessage />

            <section v-if="notices.length" aria-label="Needs attention" class="flex flex-col gap-2.5">
                <Notice v-for="(n, i) in notices" :key="i" shape="row" tone="warning" tag="Action needed" :title="n.title" :action="n.cta"
                        @action="open(n.modal, n.accountId)">{{ n.body }}</Notice>
            </section>

            <Card v-if="!accounts.length">
                <EmptyState title="No calendar connected yet">
                    Connect Microsoft 365, Google Workspace or a CalDAV server. Your displays show the rooms from it.
                    <template #actions>
                        <Button @click="connect()">Connect source</Button>
                    </template>
                </EmptyState>
            </Card>

            <template v-else>
                <section aria-label="Connected sources" class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <SourceCard v-for="a in accounts" :key="`${a.provider}-${a.id}`" :account="a"
                                @booking-method="open(a.provider === 'google' ? 'google-booking-method' : 'microsoft-booking-method', a.id)"
                                @service-account="open('service-account', a.id)"
                                @reconnect="connect(a.provider, a.permission || 'write')" />
                </section>

                <RoomCalendars :calendars="calendars" :new-display-url="urls.newDisplay" />
            </template>
        </div>

        <ConnectModal :open="modal?.type === 'connect'" :provider="modal?.provider" :permission="modal?.permission" :urls="urls" @close="close" />
        <BookingMethodModal :open="modal?.type === 'google-booking-method' || modal?.type === 'microsoft-booking-method'"
                            :provider="modal?.type === 'google-booking-method' ? 'google' : 'microsoft'" :account="account" :urls="urls" @close="close" />
        <ServiceAccountModal :open="modal?.type === 'service-account'" :account="account" :url="urls.serviceAccount" @close="close" />
        <AdminConsentModal :open="modal?.type === 'admin-consent'" :url="adminConsent?.url ?? ''" @close="close" @retry="connect('microsoft', 'write')" />
    </PortalShell>
</template>
