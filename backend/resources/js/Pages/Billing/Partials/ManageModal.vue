<script setup>
// No customer record to open a billing portal for: point at the link in the Lemon Squeezy emails.
import Modal from '../../../DesignSystem/Modal.vue';
import Button from '../../../DesignSystem/Button.vue';

defineProps({ open: Boolean });
defineEmits(['close']);
</script>

<template>
    <Modal :open="open" title="Manage your subscription" @close="$emit('close')">
        <p class="text-ink-2">You can manage your subscription in Lemon Squeezy using the order and payment emails you received. Each email has a "Manage subscription" button that works for both cloud and self-hosted subscriptions.</p>
        <p class="text-ink-2">A cloud subscription adapts every month to what you use.</p>
        <p class="text-ink-2">Cannot find the email? Write to <a href="mailto:support@spacepad.io" class="font-semibold text-navy hover:underline">support@spacepad.io</a>.</p>
        <template #footer>
            <Button variant="secondary" @click="$emit('close')">Close</Button>
        </template>
    </Modal>
</template>
