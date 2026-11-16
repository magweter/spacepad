<script setup>
// Connect a calendar account: pick the provider and what Spacepad may do, then sign in there.
// The sign-in leaves the app, so this is a real form post rather than an Inertia visit.
import { computed, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import Modal from '../../../DesignSystem/Modal.vue';
import Button from '../../../DesignSystem/Button.vue';
import ChoiceCard from '../../../Components/ChoiceCard.vue';

const props = defineProps({
    open: Boolean,
    provider: { type: String, default: null },
    permission: { type: String, default: 'write' },
    urls: { type: Object, required: true },
});
defineEmits(['close']);

const page = usePage();
const enabled = computed(() => page.props.app?.providers ?? {});
const providers = computed(() => [
    { key: 'microsoft', tile: 'M', title: 'Microsoft 365', body: 'Outlook and Exchange room mailboxes.', action: props.urls.outlookAuth },
    { key: 'google', tile: 'G', title: 'Google Workspace', body: 'Google Calendar and room resources.', action: props.urls.googleAuth },
].filter((p) => enabled.value[p.key]));

const picked = ref(null);
const access = ref('write');
const sending = ref(false);
const chosen = computed(() => providers.value.find((p) => p.key === picked.value) ?? null);
const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

watch(() => props.open, (open) => {
    if (!open) return;
    picked.value = providers.value.some((p) => p.key === props.provider) ? props.provider : (providers.value[0]?.key ?? null);
    access.value = props.permission === 'read' ? 'read' : 'write';
    sending.value = false;
}, { immediate: true });
</script>

<template>
    <Modal :open="open" title="Connect a calendar" width="560px" @close="$emit('close')">
        <form v-if="providers.length" id="connect-source" method="POST" :action="chosen?.action" class="flex flex-col gap-4" @submit="sending = true">
            <input type="hidden" name="_token" :value="csrf()">
            <input type="hidden" name="permission_type" :value="access">

            <div class="grid gap-3 sm:grid-cols-2">
                <ChoiceCard v-for="p in providers" :key="p.key" :selected="picked === p.key" @select="picked = p.key">
                    <span class="flex h-9 w-9 flex-none items-center justify-center rounded-lg bg-info-bg text-[15px] font-bold text-info">{{ p.tile }}</span>
                    <span class="flex min-w-0 flex-col">
                        <span class="font-semibold">{{ p.title }}</span>
                        <span class="text-xs text-muted">{{ p.body }}</span>
                    </span>
                </ChoiceCard>
            </div>

            <div class="flex flex-col gap-2.5">
                <span class="text-[13px] font-semibold">What may Spacepad do?</span>
                <ChoiceCard :selected="access === 'write'" @select="access = 'write'">
                    <span class="flex flex-col gap-0.5">
                        <span class="font-semibold">Read and book <span class="ml-1 text-[11px] font-semibold text-muted">Recommended</span></span>
                        <span class="text-xs text-muted">Shows what is booked and lets people book a free room from the display. Bookings are written to the calendar.</span>
                    </span>
                </ChoiceCard>
                <ChoiceCard :selected="access === 'read'" @select="access = 'read'">
                    <span class="flex flex-col gap-0.5">
                        <span class="font-semibold">Read only</span>
                        <span class="text-xs text-muted">Shows what is booked. Bookings made on a display do not appear in your calendar.</span>
                    </span>
                </ChoiceCard>
            </div>
        </form>

        <p v-else class="text-[13px] text-muted">
            Microsoft and Google are not enabled on this instance. Ask your administrator to configure them.
        </p>

        <a v-if="enabled.caldav" :href="urls.caldavCreate" class="self-start text-[13px] font-semibold text-navy hover:underline">
            Using Nextcloud, iCloud or another CalDAV server? →
        </a>

        <template #footer>
            <Button variant="ghost" @click="$emit('close')">Cancel</Button>
            <Button v-if="chosen" type="submit" form="connect-source" :disabled="sending">{{ sending ? 'Opening sign-in' : `Sign in with ${chosen.title}` }}</Button>
        </template>
    </Modal>
</template>
