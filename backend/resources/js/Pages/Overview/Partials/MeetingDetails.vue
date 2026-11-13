<script setup>
// One meeting from the timeline: when, where, who booked it, how it was booked, and the online
// meeting link when there is one.
import { computed } from 'vue';
import Modal from '../../../DesignSystem/Modal.vue';
import StatusBadge from '../../../DesignSystem/StatusBadge.vue';
import Icon from '../../../Components/Icon.vue';

const props = defineProps({ meeting: { type: Object, default: null } });
const emit = defineEmits(['close']);

const state = computed(() => ({
    past: { status: 'offline', word: 'Earlier today' },
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
                <dd>{{ meeting.tablet ? 'On the tablet at the door' : 'In the calendar' }}</dd>
                <template v-if="meeting.checkedIn || meeting.checkInUntil">
                    <dt class="text-muted">Check-in</dt>
                    <dd>{{ meeting.checkedIn ? 'Checked in' : 'Not checked in yet' }}</dd>
                </template>
                <dt class="text-muted">Online meeting</dt>
                <dd>{{ meeting.joinUrl ? `Yes, ${service}` : 'No' }}</dd>
            </dl>
        </template>
        <template v-if="meeting?.joinUrl" #footer>
            <a :href="meeting.joinUrl" target="_blank" rel="noopener" class="sp-btn sp-btn-primary sp-btn-md">
                <span class="sp-btn-icon"><Icon name="external" :size="14" /></span>Join {{ service === 'online' ? 'the meeting' : `${service} meeting` }}
            </a>
        </template>
    </Modal>
</template>
