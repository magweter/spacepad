<script setup>
// Suggest a feature: posts to roadmap.suggest, which stores it for review and mails support.
import { reactive, ref } from 'vue';
import HelpIcon from './HelpIcon.vue';
import Button from '../../DesignSystem/Button.vue';
import { errorFor, helpUrls, requestJson } from './helpPanel.js';

const form = reactive({ suggestion_title: '', suggestion_description: '' });
const errors = reactive({ suggestion_title: null, suggestion_description: null });
const sending = ref(false);
const sent = ref(false);

const field = 'w-full rounded-[10px] border border-line bg-surface px-3.5 py-2.5 text-sm focus:border-navy focus:outline-none';

async function submit() {
    if (sending.value) return;
    sending.value = true;
    errors.suggestion_title = null;
    errors.suggestion_description = null;
    try {
        const result = await requestJson(helpUrls.suggest, { method: 'POST', body: { ...form } });
        if (result.ok) {
            sent.value = true;
            form.suggestion_title = '';
            form.suggestion_description = '';
        } else if (result.status === 422) {
            errors.suggestion_title = errorFor(result, 'suggestion_title');
            errors.suggestion_description = errorFor(result, 'suggestion_description');
        } else {
            errors.suggestion_title = errorFor(result, 'suggestion_title');
        }
    } catch {
        errors.suggestion_title = 'Something went wrong. Please try again.';
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
                <p class="font-semibold">Request received, thank you.</p>
                <p class="mt-0.5 leading-relaxed text-ink-3">We read every request and reply soon. If it is a fit, it shows up on the roadmap above, where others can vote to help decide what gets built next.</p>
            </div>
        </div>
        <form v-else class="flex flex-col gap-2.5" @submit.prevent="submit">
            <div class="mb-0.5">
                <h3 class="font-semibold">Missing something?</h3>
                <p class="mt-1 text-[13px] leading-relaxed text-ink-3">Suggest a feature or idea. We review every request and add the good fits to the roadmap above.</p>
            </div>
            <label class="sr-only" for="help-suggest-title">Feature title</label>
            <input id="help-suggest-title" v-model="form.suggestion_title" type="text" required minlength="5" maxlength="150"
                   placeholder="Feature title (for example: dark mode for displays)" :aria-invalid="errors.suggestion_title ? 'true' : null" :class="field">
            <p v-if="errors.suggestion_title" class="text-xs text-reserved-text" role="alert">{{ errors.suggestion_title }}</p>
            <label class="sr-only" for="help-suggest-description">Description</label>
            <textarea id="help-suggest-description" v-model="form.suggestion_description" rows="4" maxlength="1000"
                      placeholder="Description (optional: more context, or why it matters to you)" :class="[field, 'resize-none']"></textarea>
            <p v-if="errors.suggestion_description" class="text-xs text-reserved-text" role="alert">{{ errors.suggestion_description }}</p>
            <div class="flex justify-end">
                <Button type="submit" :disabled="sending">
                    <template #icon><HelpIcon name="send" :size="16" /></template>
                    {{ sending ? 'Sending...' : 'Submit request' }}
                </Button>
            </div>
        </form>
    </section>
</template>
