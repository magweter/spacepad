<script setup>
// Upload the Google service account key that lets a Workspace account book straight onto room
// calendars. Google keeps this deliberately manual, so the steps are spelled out.
import { ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Modal from '../../../DesignSystem/Modal.vue';
import Button from '../../../DesignSystem/Button.vue';
import Notice from '../../../DesignSystem/Notice.vue';
import Icon from '../../../Components/Icon.vue';

const props = defineProps({ open: Boolean, account: { type: Object, default: null }, url: { type: String, required: true } });
const emit = defineEmits(['close']);

const form = useForm({ google_account_id: null, service_account_file: null });
const showSteps = ref(true);

watch(() => [props.open, props.account?.id], () => {
    if (!props.open) return;
    form.reset();
    form.clearErrors();
    form.google_account_id = props.account?.id ?? null;
    showSteps.value = !!props.account?.needsServiceAccount;
}, { immediate: true });

const pick = (e) => { form.service_account_file = e.target.files[0] ?? null; };

function upload() {
    form.post(props.url, { forceFormData: true, preserveScroll: true, onSuccess: () => emit('close') });
}

const link = 'font-semibold text-navy underline underline-offset-2';
</script>

<template>
    <Modal :open="open" title="Upload a Google service account" width="620px" @close="$emit('close')">
        <p class="text-muted">
            With a service account, bookings from a display go straight onto the room calendar of {{ account?.email || 'your Google Workspace' }}.
            Google keeps this a manual step for security reasons; there is no simpler way to grant it yet.
        </p>

        <Notice tone="warning" title="Before you start">
            Allow service account keys in your Google Cloud organization policy.
            <a href="https://www.youtube.com/watch?v=VY_lrX5iY1U&start=0" target="_blank" rel="noopener" :class="link">Watch how</a>.
        </Notice>

        <button type="button" class="self-start text-[13px] font-semibold text-navy hover:underline" :aria-expanded="showSteps" @click="showSteps = !showSteps">
            {{ showSteps ? 'Hide the steps' : 'Show how to get the file' }}
        </button>

        <ol v-if="showSteps" class="flex list-decimal flex-col gap-1.5 rounded-lg border border-line bg-surface-raised py-3 pr-4 pl-9 text-[13px] text-ink-2">
            <li>Open the <a href="https://console.cloud.google.com/" target="_blank" rel="noopener" :class="link">Google Cloud Console</a> and create or pick a project.</li>
            <li>Enable the <strong>Calendar API</strong> under APIs and services.</li>
            <li>Go to <strong>IAM and admin</strong>, then <strong>Service accounts</strong>, and create one. Any name works; you can skip the optional permission steps.</li>
            <li>Open the new service account, go to <strong>Keys</strong>, choose <strong>Add key</strong>, <strong>Create new key</strong>, pick <strong>JSON</strong> and download the file.</li>
            <li>Copy its <strong>Unique ID</strong> (the long number on the details page).</li>
            <li>In the <a href="https://admin.google.com/" target="_blank" rel="noopener" :class="link">Google Workspace Admin console</a>, go to <strong>Security</strong>, <strong>Access and data control</strong>, <strong>API controls</strong>, <strong>Manage domain-wide delegation</strong> and add a new entry with that ID.</li>
            <li>
                Give it these scopes:
                <code class="mt-1 block text-xs break-all">https://www.googleapis.com/auth/calendar.readonly</code>
                <code class="block text-xs break-all">https://www.googleapis.com/auth/calendar.events</code>
            </li>
            <li>Share each room calendar with the service account email (client_email in the file) and give it <strong>Make changes to events</strong>.</li>
            <li>Upload the JSON file below.</li>
        </ol>

        <form id="service-account" class="flex flex-col gap-1.5" @submit.prevent="upload">
            <span class="text-[13px] font-semibold">Service account key (JSON)</span>
            <label class="flex cursor-pointer items-center gap-3 rounded-[10px] border border-dashed border-offline-border bg-surface px-3.5 py-3 hover:border-navy">
                <Icon name="upload" :size="16" class="text-muted" />
                <span class="min-w-0 flex-1 truncate" :class="form.service_account_file ? 'text-ink' : 'text-muted'">
                    {{ form.service_account_file?.name ?? 'Choose the file you downloaded' }}
                </span>
                <input type="file" accept=".json,application/json" required class="sr-only" @change="pick">
            </label>
            <span v-if="form.errors.service_account_file" class="text-xs text-reserved-text">{{ form.errors.service_account_file }}</span>
            <span v-else-if="form.errors.google_account_id" class="text-xs text-reserved-text">{{ form.errors.google_account_id }}</span>
        </form>

        <template #footer>
            <Button variant="ghost" @click="$emit('close')">Skip for now</Button>
            <Button type="submit" form="service-account" :disabled="form.processing || !form.service_account_file">
                {{ form.processing ? 'Uploading' : 'Upload' }}
            </Button>
        </template>
    </Modal>
</template>
