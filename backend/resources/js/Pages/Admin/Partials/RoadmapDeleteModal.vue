<script setup>
// Confirms deleting a roadmap item or a pending suggestion. Open while `item` is set.
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import Modal from '../../../DesignSystem/Modal.vue';
import Button from '../../../DesignSystem/Button.vue';

const props = defineProps({ item: { type: Object, default: null } }); // { title, deleteUrl, kind: 'item' | 'suggestion' }
const emit = defineEmits(['close']);

const processing = ref(false);
function confirm() {
    router.delete(props.item.deleteUrl, {
        preserveScroll: true,
        onStart: () => { processing.value = true; },
        onFinish: () => { processing.value = false; emit('close'); },
    });
}
</script>

<template>
    <Modal :open="Boolean(item)" :title="item?.kind === 'suggestion' ? 'Delete this suggestion?' : 'Delete this item?'" @close="emit('close')">
        <p v-if="item" class="text-ink-2">
            <span class="font-semibold text-ink">{{ item.title }}</span> is removed from the roadmap, together with its votes. This cannot be undone.
        </p>
        <template #footer>
            <Button variant="secondary" @click="emit('close')">Keep it</Button>
            <button type="button" class="sp-btn sp-btn-md border-reserved bg-reserved text-white hover:opacity-90 disabled:opacity-50" :disabled="processing" @click="confirm">Delete</button>
        </template>
    </Modal>
</template>
