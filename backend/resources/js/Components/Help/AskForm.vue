<script setup>
// Ask a question: posts to support.ask, which stores it and mails support.
import { ref } from 'vue';
import HelpIcon from './HelpIcon.vue';
import Button from '../../DesignSystem/Button.vue';
import { errorFor, helpUrls, requestJson } from './helpPanel.js';

defineProps({ email: { type: String, default: null } });

const message = ref('');
const sending = ref(false);
const sent = ref(false);
const error = ref(null);

async function submit() {
    if (sending.value) return;
    sending.value = true;
    error.value = null;
    try {
        const result = await requestJson(helpUrls.ask, { method: 'POST', body: { message: message.value } });
        if (result.ok) {
            sent.value = true;
            message.value = '';
        } else {
            error.value = errorFor(result, 'message');
        }
    } catch {
        error.value = 'Something went wrong. Please try again.';
    } finally {
        sending.value = false;
    }
}
</script>

<template>
    <section class="rounded-[14px] border border-line bg-surface-raised p-5">
        <div v-if="sent" class="flex items-start gap-3" role="status">
            <span class="flex h-8 w-8 flex-none items-center justify-center rounded-full bg-available-bg text-available-text"><HelpIcon name="check" :size="16" /></span>
            <div>
                <p class="font-semibold">Got your message, thank you.</p>
                <p class="mt-0.5 text-ink-3">We will reply to <strong class="font-semibold text-ink-2">{{ email }}</strong>.</p>
            </div>
        </div>
        <form v-else class="flex flex-col gap-3" @submit.prevent="submit">
            <div>
                <h3 class="font-semibold">Have a question or thought?</h3>
                <p class="mt-1 text-[13px] leading-relaxed text-ink-3">
                    Something not working, something missing, or not sure if Spacepad is the right fit? Tell us. We normally reply within a day and answer every message personally.
                </p>
            </div>
            <label class="sr-only" for="help-ask-message">Your question</label>
            <textarea id="help-ask-message" v-model="message" rows="4" required minlength="10" maxlength="2000"
                      placeholder="What is your question about? Blockers, requests, honest feedback?"
                      :aria-invalid="error ? 'true' : null"
                      class="w-full resize-none rounded-[10px] border border-line bg-surface px-3.5 py-2.5 text-sm focus:border-navy focus:outline-none"></textarea>
            <p v-if="error" class="text-xs text-reserved-text" role="alert">{{ error }}</p>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <p v-if="email" class="text-xs text-muted">We will reply to <span class="font-semibold">{{ email }}</span>.</p>
                <Button type="submit" :disabled="sending" class="ml-auto">
                    <template #icon><HelpIcon name="send" :size="16" /></template>
                    {{ sending ? 'Sending...' : 'Send' }}
                </Button>
            </div>
        </form>
    </section>
</template>
