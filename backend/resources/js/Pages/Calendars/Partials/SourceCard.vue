<script setup>
// One connected calendar account: who it is, what Spacepad may do with it and what it still needs.
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';
import Icon from '../../../Components/Icon.vue';
import StatusDot from '../../../DesignSystem/StatusDot.vue';

const props = defineProps({ account: { type: Object, required: true } });
defineEmits(['booking-method', 'service-account', 'reconnect']);

const tile = {
    microsoft: { letter: 'M', classes: 'bg-info-bg text-info' },
    google: { letter: 'G', classes: 'bg-line-soft text-ink-2' },
    caldav: { letter: 'C', classes: 'bg-line-soft text-ink-2' },
};

const a = computed(() => props.account);
const attention = computed(() => a.value.needsBookingMethod || a.value.needsServiceAccount);
const state = computed(() => {
    if (a.value.status === 'error') return { dot: 'reserved', text: 'text-reserved-text', word: 'Sign in again' };
    if (attention.value) return { dot: 'checkin', text: 'text-checkin-text', word: 'Attention' };
    return { dot: 'available', text: 'text-available-text', word: 'Connected' };
});

const subtitle = computed(() => [a.value.provider === 'caldav' ? a.value.host : null, a.value.email].filter(Boolean).join(', ') || a.value.name);
const access = computed(() => a.value.permission === 'write' ? 'Read and book' : a.value.permission === 'read' ? 'Read only' : '-');
const booking = computed(() => {
    if (a.value.permission !== 'write') return null;
    if (a.value.needsBookingMethod) return 'Not chosen yet';
    if (a.value.choosesBookingMethod) return a.value.bookingMethod?.label ?? '-';
    return a.value.provider === 'caldav' ? 'On the calendar' : 'As this account';
});
const serviceAccount = computed(() => a.value.usesServiceAccount ? (a.value.needsServiceAccount ? 'Not uploaded yet' : 'Uploaded') : null);
const plural = (n, one, many) => `${n} ${n === 1 ? one : many}`;

function remove() {
    if (!confirm(`Disconnect ${a.value.email || a.value.name} from this workspace?`)) return;
    router.delete(a.value.deleteUrl, { preserveScroll: true });
}
</script>

<template>
    <article class="flex flex-col gap-3.5 rounded-xl border border-line bg-surface p-5">
        <div class="flex items-center gap-3">
            <span class="flex h-10 w-10 flex-none items-center justify-center rounded-[10px] text-[15px] font-bold" :class="tile[a.provider].classes">{{ tile[a.provider].letter }}</span>
            <span class="flex min-w-0 flex-1 flex-col">
                <span class="font-semibold">{{ a.providerLabel }}</span>
                <span class="truncate text-xs text-muted" :title="subtitle">{{ subtitle }}</span>
            </span>
            <span class="inline-flex flex-none items-center gap-1.5 text-xs font-semibold" :class="state.text"><StatusDot :status="state.dot" />{{ state.word }}</span>
        </div>

        <dl class="grid grid-cols-2 gap-x-3 gap-y-2.5 text-[13px]">
            <div class="flex flex-col"><dt class="text-xs text-muted">Rooms</dt><dd class="font-medium">{{ plural(a.calendars, 'calendar', 'calendars') }} in use</dd></div>
            <div class="flex flex-col"><dt class="text-xs text-muted">Access</dt><dd class="font-medium">{{ access }}</dd></div>
            <div class="flex flex-col"><dt class="text-xs text-muted">Displays</dt><dd class="font-medium">{{ a.displays }}</dd></div>
            <div v-if="booking" class="flex flex-col">
                <dt class="text-xs text-muted">Books</dt>
                <dd class="font-medium" :class="a.needsBookingMethod ? 'text-checkin-text' : ''">{{ booking }}</dd>
            </div>
            <div v-if="serviceAccount" class="flex flex-col">
                <dt class="text-xs text-muted">Service account</dt>
                <dd class="font-medium" :class="a.needsServiceAccount ? 'text-checkin-text' : ''">{{ serviceAccount }}</dd>
            </div>
        </dl>

        <p v-if="a.status === 'error'" class="rounded-lg border border-reserved-border bg-reserved-bg px-2.5 py-2 text-xs text-reserved-text">
            Spacepad lost access to this account. Its displays stop updating until you sign in again.
        </p>

        <div class="flex flex-wrap items-center gap-x-3 gap-y-1 border-t border-line-soft pt-3 text-xs font-semibold">
            <button v-if="a.status === 'error'" type="button" class="text-navy hover:underline" @click="$emit('reconnect')">Sign in again</button>
            <button v-if="a.choosesBookingMethod" type="button" class="text-navy hover:underline" @click="$emit('booking-method')">Booking method</button>
            <button v-if="a.usesServiceAccount" type="button" class="text-navy hover:underline" @click="$emit('service-account')">
                {{ a.needsServiceAccount ? 'Upload service account' : 'Replace service account' }}
            </button>
            <span class="flex-1"></span>
            <button v-if="a.canDelete" type="button" class="inline-flex items-center gap-1 text-muted hover:text-reserved-text" @click="remove">
                <Icon name="trash" :size="14" />Disconnect
            </button>
            <span v-else-if="a.calendars" class="inline-flex cursor-not-allowed items-center gap-1 font-medium text-muted" title="Remove the displays that use this account first">
                <Icon name="trash" :size="14" />In use
            </span>
        </div>
    </article>
</template>
