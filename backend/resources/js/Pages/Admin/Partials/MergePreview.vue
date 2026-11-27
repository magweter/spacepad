<script setup>
// What a merge would do, and the form that does it: type the source name, then confirm.
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Button from '../../../DesignSystem/Button.vue';
import Card from '../../../DesignSystem/Card.vue';
import Modal from '../../../DesignSystem/Modal.vue';
import Notice from '../../../DesignSystem/Notice.vue';

const props = defineProps({ preview: { type: Object, required: true }, url: { type: String, required: true } });
const emit = defineEmits(['merged']);

const form = useForm({ confirm_name: '' });
const confirming = ref(false);
const field = ref(null);

function ask() {
    if (field.value && !field.value.reportValidity()) return;
    confirming.value = true;
}

// The same fields the preview was made with; an option is only sent when it is on.
function merge() {
    const p = props.preview;
    form.transform((data) => ({
        source_workspace_id: p.source.id,
        target_workspace_id: p.target.id,
        ...Object.fromEntries(Object.entries(p.options).filter(([, on]) => on).map(([key]) => [key, '1'])),
        confirm_name: data.confirm_name,
    })).post(props.url, {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => { form.reset(); emit('merged'); },
        onFinish: () => { confirming.value = false; },
    });
}

const block = 'rounded-[10px] border border-line-soft bg-surface p-4';
const title = 'mb-2 text-[13px] font-semibold text-ink';
</script>

<template>
    <Card class="border-amber-300 bg-checkin-bg">
        <h2 class="font-display text-base font-bold tracking-tight">Preview: "{{ preview.source.name }}" &rarr; "{{ preview.target.name }}"</h2>
        <p class="mt-1 text-[13px] text-ink-2">Nothing has been changed yet.</p>

        <div class="mt-4 flex flex-col gap-3">
            <div :class="block">
                <h3 :class="title">Rows that will move</h3>
                <p v-if="!preview.counts.length" class="text-[13px] text-muted">The source workspace is empty.</p>
                <ul v-else class="space-y-0.5 text-[13px] text-ink-2">
                    <li v-for="c in preview.counts" :key="c.label">{{ c.count }} &times; {{ c.label }}</li>
                </ul>
            </div>

            <div :class="block">
                <h3 :class="title">Members</h3>
                <p v-if="!preview.members.length" class="text-[13px] text-muted">The source workspace has no members.</p>
                <ul v-else class="space-y-0.5 text-[13px] text-ink-2">
                    <li v-for="m in preview.members" :key="m.email">{{ m.email }}: {{ m.from }} &rarr; {{ m.to }}</li>
                </ul>
            </div>

            <div v-if="preview.nameCollisions.length" :class="block">
                <h3 :class="title">Name collisions</h3>
                <p v-for="c in preview.nameCollisions" :key="c.type" class="text-[13px] text-ink-2">{{ c.type }}: {{ c.items.join(', ') }}</p>
            </div>

            <div v-if="preview.duplicateAccounts.length" :class="block">
                <h3 :class="title">Duplicate calendar accounts</h3>
                <p class="mb-2 text-xs text-muted">
                    These will be moved as additional connected accounts, not merged. Their OAuth tokens differ and re-pointing
                    calendars would break sync. Disconnect one by hand afterwards.
                </p>
                <p v-for="c in preview.duplicateAccounts" :key="c.type" class="text-[13px] text-ink-2">{{ c.type }}: {{ c.items.join(', ') }}</p>
            </div>

            <div :class="block">
                <h3 :class="title">Billing</h3>
                <p class="text-[13px] text-ink-2">
                    Source has an active subscription: <strong>{{ preview.sourceHasSubscription ? 'yes' : 'no' }}</strong>.
                    Target has an active subscription: <strong>{{ preview.targetHasSubscription ? 'yes' : 'no' }}</strong>.
                </p>
                <Notice v-if="preview.sourceHasSubscription" tone="danger" class="mt-3">
                    The source subscription is <strong>not</strong> cancelled by this merge, and the source workspace will not be
                    deleted while it is billed. Cancel it in Lemon Squeezy or switch the account to manual billing yourself.
                </Notice>
            </div>
        </div>

        <form class="mt-5 flex flex-col gap-3" @submit.prevent="ask">
            <div class="flex max-w-sm flex-col gap-1.5">
                <label for="confirm_name" class="text-[13px] font-medium text-ink">Type <span class="font-mono">{{ preview.source.name }}</span> to confirm:</label>
                <input id="confirm_name" ref="field" v-model="form.confirm_name" type="text" name="confirm_name" autocomplete="off" required
                       class="w-full rounded-lg border bg-surface px-3 py-2 text-[13px] text-ink focus:outline-none"
                       :class="form.errors.confirm_name ? 'border-reserved focus:border-reserved' : 'border-line focus:border-navy'"
                       :aria-invalid="!!form.errors.confirm_name">
                <p v-if="form.errors.confirm_name" class="text-xs text-reserved-text">{{ form.errors.confirm_name }}</p>
                <p v-for="key in ['source_workspace_id', 'target_workspace_id']" v-show="form.errors[key]" :key="key" class="text-xs text-reserved-text">{{ form.errors[key] }}</p>
            </div>
            <div><Button type="submit" variant="danger" :disabled="form.processing">Merge workspaces</Button></div>
        </form>

        <Modal :open="confirming" title="Merge these workspaces?" @close="confirming = false">
            <p class="text-ink-2">
                Everything in <span class="font-semibold text-ink">{{ preview.source.name }}</span> moves into
                <span class="font-semibold text-ink">{{ preview.target.name }}</span>. This cannot be undone.
            </p>
            <template #footer>
                <Button variant="secondary" @click="confirming = false">Cancel</Button>
                <Button variant="danger" :disabled="form.processing" @click="merge">Merge workspaces</Button>
            </template>
        </Modal>
    </Card>
</template>
