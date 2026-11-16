<script setup>
// A cancellation made outside the app still gets its one question, the next time an owner or
// admin opens the portal.
import { computed } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import Icon from '../../../Components/Icon.vue';
import Button from '../../../DesignSystem/Button.vue';

const props = defineProps({ card: Object });

const form = useForm({ reason: null, details: '' });
const prompt = computed(() => props.card.reasons.find((r) => r.value === form.reason)?.prompt);

function send() {
    form.transform((data) => (prompt.value ? data : { reason: data.reason })).post(props.card.urls.feedback, { preserveScroll: true });
}
</script>

<template>
    <div class="sp-card flex flex-col gap-3 px-5 py-[18px]">
        <div class="flex items-start justify-between gap-3">
            <h2 class="font-display text-base font-bold tracking-tight">Sorry to see you go</h2>
            <button type="button" aria-label="Dismiss" class="text-muted hover:text-ink" @click="router.post(card.urls.dismiss, {}, { preserveScroll: true })"><Icon name="close" :size="16" /></button>
        </div>
        <p class="text-[13px] text-muted">Your subscription is cancelled. Would you tell us why? One answer is enough.</p>
        <form class="flex flex-col gap-2" @submit.prevent="send">
            <label v-for="r in card.reasons" :key="r.value" class="flex cursor-pointer items-center gap-2.5 rounded-lg border px-3 py-2 text-[13px]"
                   :class="form.reason === r.value ? 'border-navy bg-surface-raised' : 'border-line hover:bg-surface-raised'">
                <input v-model="form.reason" type="radio" name="reason" :value="r.value" class="accent-navy" required>{{ r.label }}
            </label>
            <label v-if="prompt" class="flex flex-col gap-1">
                <span class="text-[13px] font-semibold">{{ prompt }}</span>
                <textarea v-model="form.details" rows="2" maxlength="2000" :required="form.reason === 'other'"
                          class="rounded-[10px] border border-offline-border px-3 py-2 text-sm focus:border-navy focus:outline-none"></textarea>
            </label>
            <span v-if="form.errors.reason || form.errors.details" class="text-xs text-reserved-text">{{ form.errors.reason || form.errors.details }}</span>
            <Button type="submit" class="self-start" :disabled="!form.reason || form.processing">Send</Button>
        </form>
    </div>
</template>
