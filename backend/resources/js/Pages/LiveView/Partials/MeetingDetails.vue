<script setup>
// One meeting from the timeline: when, where, who booked it, how it was booked, and the online
// meeting link when there is one. What the tablet at the door can do with it (check in, extend,
// end or cancel) can be done here too, under the room's own rules.
import { computed, ref, watch } from 'vue';
import Modal from '../../../DesignSystem/Modal.vue';
import Button from '../../../DesignSystem/Button.vue';
import { requestJson } from '../../../Composables/http';
import StatusBadge from '../../../DesignSystem/StatusBadge.vue';
import Icon from '../../../Components/Icon.vue';

const props = defineProps({ meeting: { type: Object, default: null }, now: { type: Date, required: true } });
const emit = defineEmits(['close', 'done']);

const busy = ref('');
const error = ref('');
const confirming = ref(false);
watch(() => props.meeting, () => { busy.value = ''; error.value = ''; confirming.value = false; });

// Same rules as the tablet: check in while it waits for one, extend while it runs, end it while it
// runs or cancel it before it starts, each only when the room allows it. From a distance only an
// owner or admin changes someone else's meeting; a member changes what they booked themselves.
const can = computed(() => {
    const m = props.meeting;
    const a = m?.actions ?? {};
    if (!m || m.end <= props.now) return { checkIn: false, extend: false, cancel: false };
    const mayChange = !!a.manage || !!m.yours;
    return {
        checkIn: !!a.checkIn && m.tone === 'checkin' && !m.checkedIn,
        extend: mayChange && !!a.extend && m.live,
        cancel: mayChange && (a.cancel === 'all' || (a.cancel === 'tablet_only' && m.tablet)),
    };
});

async function act(kind, request, message) {
    busy.value = kind;
    error.value = '';
    try {
        await request();
        emit('done', message);
    } catch (e) {
        error.value = e.message || 'That did not work. Try again.';
    } finally {
        busy.value = '';
        confirming.value = false;
    }
}
const url = (suffix = '') => `${props.meeting.eventsUrl}/${encodeURIComponent(props.meeting.id)}${suffix}`;
const checkIn = () => act('checkIn', () => requestJson('POST', url('/check-in')), `Checked in to ${props.meeting.title} in ${props.meeting.room}.`);
const extend = (minutes) => act(`extend${minutes}`, () => requestJson('POST', url('/extend'), { body: { new_end: new Date(props.meeting.end.getTime() + minutes * 60000).toISOString() } }),
    `${props.meeting.title} now runs ${minutes} minutes longer.`);
const cancel = () => act('cancel', () => requestJson('DELETE', url()), props.meeting.live ? `${props.meeting.title} has ended. The room is free.` : `${props.meeting.title} is cancelled.`);

const state = computed(() => ({
    past: { status: 'offline', word: 'Over' },
    busy: { status: 'reserved', word: 'In use now' },
    checkin: { status: 'checkin', word: 'Waiting for check-in' },
    booked: { status: 'offline', word: 'Coming up' },
}[props.meeting?.tone] ?? { status: 'offline', word: '' }));

// Which online meeting the link opens, by its address.
const service = computed(() => {
    const url = props.meeting?.joinUrl ?? '';
    if (url.includes('teams.microsoft') || url.includes('teams.live')) return 'Teams';
    if (url.includes('zoom.us')) return 'Zoom';
    if (url.includes('meet.google')) return 'Google Meet';
    return 'online';
});
const duration = computed(() => {
    const m = props.meeting?.minutes ?? 0;
    return m >= 60 ? `${Math.floor(m / 60)} h${m % 60 ? ` ${m % 60} min` : ''}` : `${m} min`;
});
</script>

<template>
    <Modal :open="!!meeting" :title="meeting?.title ?? 'Meeting'" width="460px" @close="emit('close')">
        <template v-if="meeting">
            <StatusBadge :status="state.status" class="self-start">{{ state.word }}</StatusBadge>

            <dl class="grid grid-cols-[110px_1fr] gap-x-3 gap-y-2.5 text-[13px]">
                <dt class="text-muted">When</dt>
                <dd class="font-medium tabular-nums">{{ meeting.time }} <span class="font-normal text-muted">({{ duration }})</span></dd>
                <dt class="text-muted">Room</dt>
                <dd><a :href="meeting.roomUrl" class="font-medium text-navy hover:underline">{{ meeting.room }}</a></dd>
                <template v-if="meeting.organizer">
                    <dt class="text-muted">Booked by</dt>
                    <dd class="font-medium">{{ meeting.organizer }}</dd>
                </template>
                <template v-if="meeting.location && meeting.location !== meeting.room">
                    <dt class="text-muted">Location</dt>
                    <dd>{{ meeting.location }}</dd>
                </template>
                <dt class="text-muted">Booked</dt>
                <dd>{{ meeting.tablet ? 'Through Spacepad (tablet or portal)' : 'In the calendar' }}</dd>
                <template v-if="meeting.checkedIn || meeting.checkInUntil">
                    <dt class="text-muted">Check-in</dt>
                    <dd>{{ meeting.checkedIn ? 'Checked in' : 'Not checked in yet' }}</dd>
                </template>
                <dt class="text-muted">Online meeting</dt>
                <dd>{{ meeting.joinUrl ? `Yes, ${service}` : 'No' }}</dd>
            </dl>

            <p v-if="error" role="alert" class="rounded-lg bg-reserved-bg px-3 py-2 text-[13px] text-reserved-text">{{ error }}</p>
            <div v-if="confirming" class="flex flex-wrap items-center gap-2 rounded-lg border border-reserved-border bg-reserved-bg px-3 py-2.5 text-[13px] text-reserved-text">
                <span class="flex-1 font-medium">{{ meeting.live ? 'End this meeting now? The room becomes free.' : 'Cancel this meeting? It is removed from the room\'s calendar.' }}</span>
                <Button size="sm" variant="secondary" @click="confirming = false">Keep it</Button>
                <Button size="sm" variant="danger" :disabled="!!busy" @click="cancel">{{ busy === 'cancel' ? 'One moment...' : meeting.live ? 'Yes, end it' : 'Yes, cancel it' }}</Button>
            </div>
        </template>
        <template v-if="meeting && (meeting.joinUrl || can.checkIn || can.extend || (can.cancel && !confirming))" #footer>
            <Button v-if="can.cancel && !confirming" variant="ghost" class="mr-auto text-reserved-text" @click="confirming = true">{{ meeting.live ? 'End meeting' : 'Cancel meeting' }}</Button>
            <template v-if="can.extend">
                <Button v-for="m in [15, 30]" :key="m" variant="secondary" :disabled="!!busy" @click="extend(m)">{{ busy === `extend${m}` ? 'One moment...' : `+${m} min` }}</Button>
            </template>
            <Button v-if="can.checkIn" :disabled="!!busy" @click="checkIn">{{ busy === 'checkIn' ? 'One moment...' : 'Check in' }}</Button>
            <a v-if="meeting.joinUrl" :href="meeting.joinUrl" target="_blank" rel="noopener" class="sp-btn sp-btn-md" :class="can.checkIn ? 'sp-btn-secondary' : 'sp-btn-primary'">
                <span class="sp-btn-icon"><Icon name="external" :size="14" /></span>Join {{ service === 'online' ? 'the meeting' : `${service} meeting` }}
            </a>
        </template>
    </Modal>
</template>
