<script setup>
// Confirms signing in as someone else. The server answers with a full page load, so the
// impersonation banner (part of the root view) shows up on the dashboard.
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import Button from '../../../DesignSystem/Button.vue';
import Modal from '../../../DesignSystem/Modal.vue';

const props = defineProps({ user: { type: Object, default: null } });
const emit = defineEmits(['close']);

const busy = ref(false);
function confirm() {
    router.post(props.user.urls.impersonate, {}, {
        onStart: () => { busy.value = true; },
        onFinish: () => { busy.value = false; emit('close'); },
    });
}
</script>

<template>
    <Modal :open="!!user" title="Impersonate user" @close="emit('close')">
        <p v-if="user" class="text-ink-2">
            Are you sure you want to impersonate <span class="font-semibold text-ink">{{ user.email }}</span>?
            You see the portal as they do until you choose Stop impersonating in the banner at the top.
        </p>
        <template #footer>
            <Button variant="secondary" @click="emit('close')">Cancel</Button>
            <Button :disabled="busy" @click="confirm">Impersonate</Button>
        </template>
    </Modal>
</template>
