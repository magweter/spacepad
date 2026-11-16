<script setup>
// Cancelling asks one thing first: why. Pro stays until the end of the paid period, which the
// modal says before anything is final.
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Modal from '../../../DesignSystem/Modal.vue';
import Button from '../../../DesignSystem/Button.vue';

const props = defineProps({ open: Boolean, endsOn: String, reasons: Array, url: String });
const emit = defineEmits(['close']);

const form = useForm({ reason: null, details: '' });
const prompt = computed(() => props.reasons.find((r) => r.value === form.reason)?.prompt);

function submit() {
    form.transform((data) => (prompt.value ? data : { reason: data.reason }))
        .post(props.url, { preserveScroll: true, preserveState: true, onSuccess: () => emit('close') });
}
</script>

<template>
    <Modal :open="open" title="Cancel subscription" @close="emit('close')">
        <form id="cancel-subscription" class="flex flex-col gap-3" @submit.prevent="submit">
            <p class="text-ink-2">Before you go: why are you cancelling? One answer is enough, and it helps us make Spacepad better.</p>
            <fieldset class="flex flex-col gap-2">
                <legend class="sr-only">Why are you cancelling?</legend>
                <label v-for="r in reasons" :key="r.value" class="flex cursor-pointer items-center gap-2.5 rounded-lg border px-3 py-2 text-[13px]"
                       :class="form.reason === r.value ? 'border-navy bg-surface-raised' : 'border-line hover:bg-surface-raised'">
                    <input v-model="form.reason" type="radio" name="reason" :value="r.value" class="accent-navy" required>{{ r.label }}
                </label>
            </fieldset>
            <label v-if="prompt" class="flex flex-col gap-1">
                <span class="text-[13px] font-semibold">{{ prompt }}</span>
                <textarea v-model="form.details" rows="2" maxlength="2000" :required="form.reason === 'other'"
                          class="rounded-[10px] border border-offline-border px-3 py-2 text-sm focus:border-navy focus:outline-none"></textarea>
            </label>
            <span v-if="form.errors.reason || form.errors.details" class="text-xs text-reserved-text">{{ form.errors.reason || form.errors.details }}</span>
            <p class="text-[13px] text-muted">
                Pro stays active until {{ endsOn ?? 'the end of the current period' }}. After that the workspace moves to the free plan, with one active display.
            </p>
        </form>
        <template #footer>
            <Button variant="secondary" @click="emit('close')">Keep subscription</Button>
            <button type="submit" form="cancel-subscription" class="sp-btn sp-btn-md border-reserved bg-reserved text-white hover:opacity-90 disabled:opacity-50" :disabled="!form.reason || form.processing">Cancel subscription</button>
        </template>
    </Modal>
</template>
