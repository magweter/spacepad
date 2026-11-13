<script setup>
// Deleting a user: type their email, then confirm once more. Cannot be undone.
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Button from '../../../DesignSystem/Button.vue';
import Card from '../../../DesignSystem/Card.vue';
import Modal from '../../../DesignSystem/Modal.vue';

const props = defineProps({ email: { type: String, required: true }, url: { type: String, required: true } });

const form = useForm({ confirm_email: '' });
const confirming = ref(false);
const field = ref(null);

function ask() {
    // The browser checks the field first, as the old form did (required, an email address).
    if (field.value && !field.value.reportValidity()) return;
    confirming.value = true;
}
function destroy() {
    form.delete(props.url, { onFinish: () => { confirming.value = false; } });
}

const lost = [
    'All connected accounts (Outlook, Google, CalDAV)',
    'All displays and their settings',
    'All devices',
    'All calendars and events',
    'All rooms',
    'All workspace memberships',
    'All personal access tokens',
];
</script>

<template>
    <Card class="border-reserved-border">
        <h2 class="font-display text-base font-bold tracking-tight text-reserved-text">Delete user account</h2>
        <p class="mt-2 text-[13px] text-ink-2">This action cannot be undone. All data associated with this user will be permanently deleted:</p>
        <ul class="mt-2 list-inside list-disc space-y-1 text-[13px] text-ink-2">
            <li v-for="l in lost" :key="l">{{ l }}</li>
        </ul>

        <form class="mt-4 flex flex-col gap-3" @submit.prevent="ask">
            <div class="flex flex-col gap-1.5">
                <label for="confirm_email" class="text-[13px] font-medium text-ink">To confirm deletion, please type the user's email address:</label>
                <input id="confirm_email" ref="field" v-model="form.confirm_email" type="email" name="confirm_email" required autocomplete="off" :placeholder="email"
                       class="w-full rounded-lg border bg-surface px-3 py-2 text-[13px] text-ink focus:outline-none"
                       :class="form.errors.confirm_email ? 'border-reserved focus:border-reserved' : 'border-line focus:border-navy'"
                       :aria-invalid="!!form.errors.confirm_email" aria-describedby="confirm_email_error">
                <p v-if="form.errors.confirm_email" id="confirm_email_error" class="text-xs text-reserved-text">{{ form.errors.confirm_email }}</p>
            </div>
            <div class="flex justify-end">
                <Button type="submit" variant="danger" :disabled="form.processing">Permanently delete user</Button>
            </div>
        </form>

        <Modal :open="confirming" title="Delete this user?" @close="confirming = false">
            <p class="text-ink-2">
                Are you absolutely sure you want to delete <span class="font-semibold text-ink">{{ email }}</span>? This action cannot be undone.
            </p>
            <template #footer>
                <Button variant="secondary" @click="confirming = false">Cancel</Button>
                <Button variant="danger" :disabled="form.processing" @click="destroy">Permanently delete user</Button>
            </template>
        </Modal>
    </Card>
</template>
