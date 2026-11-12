<script setup>
// The Microsoft 365 tenant needs an admin to approve Spacepad once: hand over the link to send.
import { computed, ref } from 'vue';
import Modal from '../../../DesignSystem/Modal.vue';
import Button from '../../../DesignSystem/Button.vue';
import Icon from '../../../Components/Icon.vue';

const props = defineProps({ open: Boolean, url: { type: String, default: '' } });
const emit = defineEmits(['close', 'retry']);

const copied = ref(false);
async function copy(text) {
    try {
        await navigator.clipboard.writeText(text);
        copied.value = true;
        setTimeout(() => { copied.value = false; }, 2000);
    } catch (e) {
        console.error('Copying failed', e);
    }
}

const email = computed(() => [
    'Hi,',
    '',
    'I would like to connect Spacepad (a room display app) to our Microsoft 365 calendars. Our organization asks an admin to approve new apps first.',
    'Could you open the link below and click Accept? It is a one-time approval for the whole organization.',
    '',
    props.url,
    '',
    'Thanks!',
].join('\n'));

const steps = [
    'Copy the link below and send it to your IT admin.',
    'Your admin opens it, signs in and clicks Accept.',
    'Come back here and connect again. It works straight away.',
];
</script>

<template>
    <Modal :open="open" title="Your IT admin needs to approve Spacepad" width="560px" @close="emit('close')">
        <p class="text-muted">
            Your Microsoft 365 organization lets an admin approve new apps before they connect. This is a one-time step;
            after it, everyone in your organization can connect Spacepad.
        </p>

        <ol class="flex flex-col gap-2">
            <li v-for="(step, i) in steps" :key="i" class="flex items-start gap-2.5 text-[13px]">
                <span class="flex h-5 w-5 flex-none items-center justify-center rounded-full bg-navy text-[11px] font-bold text-white">{{ i + 1 }}</span>
                <span>{{ step }}</span>
            </li>
        </ol>

        <div class="flex flex-col gap-1.5">
            <span class="text-[13px] font-semibold">Approval link for your admin</span>
            <div class="flex items-center gap-2 rounded-[10px] border border-line bg-surface-raised py-2 pr-2 pl-3.5">
                <span class="min-w-0 flex-1 truncate font-mono text-xs text-ink-2" :title="url">{{ url }}</span>
                <Button variant="secondary" size="sm" @click="copy(url)"><template #icon><Icon :name="copied ? 'check' : 'copy'" :size="14" /></template>{{ copied ? 'Copied' : 'Copy' }}</Button>
            </div>
        </div>

        <details class="text-[13px]">
            <summary class="cursor-pointer font-semibold text-navy">Need an email to send to your admin?</summary>
            <pre class="mt-2 rounded-lg border border-line bg-surface-raised p-3 font-sans text-xs whitespace-pre-wrap text-ink-2">{{ email }}</pre>
            <button type="button" class="mt-1.5 text-xs font-semibold text-navy hover:underline" @click="copy(email)">Copy the email</button>
        </details>

        <template #footer>
            <Button variant="ghost" @click="emit('close')">Close</Button>
            <Button @click="emit('retry')">Connect again</Button>
        </template>
    </Modal>
</template>
