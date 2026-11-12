<script setup>
// How a business account books rooms from a display. Microsoft: as the account or straight on
// the room calendar after admin consent. Google: as the account or through a service account.
import { computed, watch } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import Modal from '../../../DesignSystem/Modal.vue';
import Button from '../../../DesignSystem/Button.vue';
import ChoiceCard from '../../../Components/ChoiceCard.vue';

const props = defineProps({
    open: Boolean,
    provider: { type: String, default: 'microsoft' }, // microsoft | google
    account: { type: Object, default: null },
    urls: { type: Object, required: true },
});
const emit = defineEmits(['close']);

const page = usePage();
const selfHosted = computed(() => !!page.props.app?.selfHosted);

const options = computed(() => props.provider === 'google'
    ? [
        { value: 'user_account', title: 'As the connected account', recommended: true,
          body: 'Simple to set up. Bookings appear in the calendar of the account you connected, which is invited to every booking. A dedicated account for Spacepad works best.' },
        { value: 'service_account', title: 'With a service account',
          body: 'Bookings go straight onto the room calendar, without a personal calendar in between. Needs a Google Workspace admin to set up a service account.' },
    ]
    : [
        { value: 'admin_consent', title: 'On the room calendar', recommended: true,
          body: 'Bookings go straight onto the room calendar and never show up in a personal mailbox. Your Microsoft 365 admin approves this once.' },
        { value: 'user_account', title: 'As the connected account',
          body: 'Simple to set up. Bookings appear in the calendar of the account you connected, which organizes every booking. A dedicated account for Spacepad works best.' },
    ]);

const form = useForm({ id: null, booking_method: null });

watch(() => [props.open, props.account?.id], () => {
    if (!props.open) return;
    form.clearErrors();
    form.id = props.account?.id ?? null;
    form.booking_method = props.account?.bookingMethod?.value ?? options.value.find((o) => o.recommended).value;
}, { immediate: true });

function save() {
    const [url, key] = props.provider === 'google'
        ? [props.urls.googleBookingMethod, 'google_account_id']
        : [props.urls.microsoftBookingMethod, 'outlook_account_id'];

    // When the server asks for a next step (admin consent, the service account upload), the page
    // opens that modal from the new props; otherwise this one is done.
    form.transform((data) => ({ [key]: data.id, booking_method: data.booking_method }))
        .post(url, { preserveScroll: true, onSuccess: (p) => { if (!p.props.openModal && !p.props.adminConsent) emit('close'); } });
}
</script>

<template>
    <Modal :open="open" title="How should displays book rooms?" width="560px" @close="$emit('close')">
        <p class="text-muted">
            For {{ account?.email || 'this account' }}. Booking from a display waits for this choice.
        </p>

        <div class="flex flex-col gap-2.5">
            <ChoiceCard v-for="o in options" :key="o.value" :selected="form.booking_method === o.value" @select="form.booking_method = o.value">
                <span class="flex flex-col gap-0.5">
                    <span class="font-semibold">{{ o.title }} <span v-if="o.recommended" class="ml-1 text-[11px] font-semibold text-muted">Recommended</span></span>
                    <span class="text-xs text-muted">{{ o.body }}</span>
                    <span v-if="o.value === 'admin_consent' && selfHosted" class="mt-1 text-xs text-checkin-text">
                        Self-hosted: first add Calendars.ReadWrite as an application permission (not delegated) to your Azure app registration
                        and grant admin consent in the Azure portal. AZURE_AD_CLIENT_ID and AZURE_AD_CLIENT_SECRET must be set.
                    </span>
                </span>
            </ChoiceCard>
        </div>

        <p v-if="Object.keys(form.errors).length" class="text-xs text-reserved-text">{{ Object.values(form.errors)[0] }}</p>

        <template #footer>
            <Button variant="ghost" @click="$emit('close')">Cancel</Button>
            <Button :disabled="form.processing || !form.id" @click="save">{{ form.processing ? 'Saving' : 'Continue' }}</Button>
        </template>
    </Modal>
</template>
