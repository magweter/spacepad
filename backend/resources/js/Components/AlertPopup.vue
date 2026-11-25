<script setup>
// An alert the admin marked as a popup: it opens once for every user, and "Got it" (or closing it)
// remembers that for this user. The alert stays a row in the portal while it runs.
import { computed, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import Modal from '../DesignSystem/Modal.vue';
import Button from '../DesignSystem/Button.vue';
import AlertRow from './AlertRow.vue';

const page = usePage();
const closed = ref([]);
const alert = computed(() => (page.props.alerts ?? []).find((a) => a.popup && !closed.value.includes(a.id)));

function dismiss() {
    const a = alert.value;
    if (!a) return;
    closed.value.push(a.id);
    router.post(a.dismissUrl, {}, { preserveScroll: true, preserveState: true, only: ['alerts'] });
}
</script>

<template>
    <Modal :open="!!alert" title="A message from Spacepad" @close="dismiss">
        <AlertRow v-if="alert" :tone="alert.tone" :title="alert.title" :body="alert.body" />
        <template #footer>
            <Button @click="dismiss">Got it</Button>
        </template>
    </Modal>
</template>
