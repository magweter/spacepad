<script setup>
// Deleting the account takes the person out of every workspace. Data lives in a workspace, so a
// shared workspace keeps its displays; a workspace with nobody left in it goes with the account.
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Button from '../../../DesignSystem/Button.vue';
import SettingRow from '../../../DesignSystem/SettingRow.vue';
import Modal from '../../../DesignSystem/Modal.vue';

const props = defineProps({ email: String, url: String });

const open = ref(false);
const form = useForm({ confirm_email: '' });
const submit = () => form.delete(props.url, { preserveState: true });
</script>

<template>
    <section class="sp-card gap-0 border-reserved-border px-[18px] py-1.5">
        <div class="flex flex-col gap-0.5 border-b border-reserved-border pt-3 pb-1.5">
            <h2 class="text-[15px] font-semibold text-reserved-text">Danger zone</h2>
            <span class="text-xs text-muted">This cannot be undone.</span>
        </div>
        <SettingRow label="Delete account" help="Permanently delete your account and everything only you have access to">
            <Button variant="danger" @click="open = true">Delete my account</Button>
        </SettingRow>
    </section>

    <Modal :open="open" title="Delete your account?" @close="open = false">
        <form id="delete-account" class="flex flex-col gap-3" @submit.prevent="submit">
            <p class="text-ink-2">This permanently deletes your account. It cannot be undone. Along with it go:</p>
            <ul class="list-inside list-disc text-[13px] text-ink-2">
                <li>Your membership of every workspace</li>
                <li>Workspaces where you are the only member, with their calendar accounts, displays, devices, rooms and calendars</li>
            </ul>
            <p class="text-[13px] text-muted">Workspaces shared with colleagues keep everything in them. Where you are the owner, ownership passes to a colleague.</p>
            <label class="flex flex-col gap-1">
                <span class="text-[13px] font-semibold">Type your email address to confirm</span>
                <input v-model="form.confirm_email" type="email" autocomplete="off" required :placeholder="email"
                       class="rounded-lg border border-line bg-surface px-3 py-2 text-[13px] focus:border-navy focus:outline-none">
            </label>
            <span v-if="form.errors.confirm_email" class="text-xs text-reserved-text">{{ form.errors.confirm_email }}</span>
        </form>
        <template #footer>
            <Button variant="secondary" @click="open = false">Keep my account</Button>
            <button type="submit" form="delete-account" class="sp-btn sp-btn-md border-reserved bg-reserved text-white hover:opacity-90 disabled:opacity-50"
                    :disabled="form.processing || form.confirm_email !== email">Permanently delete my account</button>
        </template>
    </Modal>
</template>
