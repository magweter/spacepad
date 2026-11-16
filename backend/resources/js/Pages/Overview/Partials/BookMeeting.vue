<script setup>
// Book a room from the portal, the same booking the tablet at the door makes. Opens from a click on
// an empty spot of the timeline (room and time filled in) or from the Book a room button.
import { computed, ref, watch } from 'vue';
import Modal from '../../../DesignSystem/Modal.vue';
import Button from '../../../DesignSystem/Button.vue';
import { requestJson } from '../../../Composables/http';

const props = defineProps({
    preset: { type: Object, default: null }, // { roomId, start: Date } or null when closed
    rooms: { type: Array, default: () => [] }, // the rooms that take bookings
    now: { type: Date, required: true },
});
const emit = defineEmits(['close', 'done']);

const pad = (n) => String(n).padStart(2, '0');
const dateOf = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
const timeOf = (d) => `${pad(d.getHours())}:${pad(d.getMinutes())}`;
const toDate = (date, time) => { const [y, m, d] = date.split('-').map(Number); const [h, i] = time.split(':').map(Number); return new Date(y, m - 1, d, h, i); };

const form = ref({ roomId: null, date: '', from: '', to: '', title: '', description: '', attendees: '' });
const error = ref('');
const busy = ref(false);

watch(() => props.preset, (p) => {
    if (!p) return;
    const start = p.start ?? nextQuarter(props.now);
    const end = new Date(start.getTime() + 30 * 60000);
    form.value = { roomId: p.roomId ?? props.rooms[0]?.id ?? null, date: dateOf(start), from: timeOf(start), to: timeOf(end), title: '', description: '', attendees: '' };
    error.value = '';
});

function nextQuarter(d) {
    const q = 15 * 60000;
    return new Date(Math.ceil(d.getTime() / q) * q);
}

const start = computed(() => (form.value.date && form.value.from ? toDate(form.value.date, form.value.from) : null));
const end = computed(() => (form.value.date && form.value.to ? toDate(form.value.date, form.value.to) : null));
const minutes = computed(() => (start.value && end.value ? Math.round((end.value - start.value) / 60000) : 0));
const room = computed(() => props.rooms.find((r) => r.id === form.value.roomId));

// Attendees as in the app: email addresses separated by commas, spaces or new lines.
const attendees = computed(() => form.value.attendees.split(/[\s,;]+/).map((a) => a.trim()).filter(Boolean));
const invalidAttendee = computed(() => attendees.value.find((a) => !/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(a)));

const problem = computed(() => {
    if (!room.value) return 'Pick a room.';
    if (invalidAttendee.value) return `${invalidAttendee.value} is not a valid email address.`;
    if (!start.value || !end.value) return 'Pick a time.';
    if (minutes.value <= 0) return 'The end time has to be after the start time.';
    if (end.value <= props.now) return 'This time has already passed.';
    if (!room.value.actions?.bookAhead && start.value > new Date(props.now.getTime() + 5 * 60000)) return `${room.value.name} only takes bookings that start now. Booking ahead is switched off in its settings.`;
    return '';
});

function setLength(m) {
    if (!start.value) return;
    form.value.to = timeOf(new Date(start.value.getTime() + m * 60000));
}

async function submit() {
    if (problem.value) { error.value = problem.value; return; }
    busy.value = true;
    error.value = '';
    try {
        await requestJson('POST', room.value.urls.book, { body: {
            start: start.value.toISOString(), end: end.value.toISOString(), summary: form.value.title.trim() || null,
            description: form.value.description.trim() || null, attendees: attendees.value,
        } });
        emit('done', `${room.value.name} is booked from ${form.value.from} to ${form.value.to}.`);
    } catch (e) {
        error.value = e.message || 'The room could not be booked. Try again.';
    } finally {
        busy.value = false;
    }
}

const field = 'block w-full rounded-lg border border-line bg-surface px-3 py-2 text-sm text-ink focus:border-navy focus:outline-none';
</script>

<template>
    <Modal :open="!!preset" title="Book a room" width="440px" @close="emit('close')">
        <form id="book-meeting" novalidate class="flex flex-col gap-3.5" @submit.prevent="submit">
            <label class="flex flex-col gap-1.5">
                <span class="text-[13px] font-semibold">Room</span>
                <select v-model="form.roomId" :class="field">
                    <option v-for="r in rooms" :key="r.id" :value="r.id">{{ r.name }}</option>
                </select>
            </label>
            <label class="flex flex-col gap-1.5">
                <span class="text-[13px] font-semibold">Title</span>
                <input v-model="form.title" type="text" maxlength="255" placeholder="Reserved" :class="field" />
            </label>
            <div class="grid grid-cols-[1.4fr_1fr_1fr] gap-2.5">
                <label class="flex min-w-0 flex-col gap-1.5">
                    <span class="text-[13px] font-semibold">Date</span>
                    <input v-model="form.date" type="date" :min="dateOf(now)" :class="field" />
                </label>
                <label class="flex min-w-0 flex-col gap-1.5">
                    <span class="text-[13px] font-semibold">From</span>
                    <input v-model="form.from" type="time" step="60" :class="field" />
                </label>
                <label class="flex min-w-0 flex-col gap-1.5">
                    <span class="text-[13px] font-semibold">To</span>
                    <input v-model="form.to" type="time" step="60" :class="field" />
                </label>
            </div>
            <div class="flex flex-wrap items-center gap-1.5">
                <span class="mr-1 text-xs text-muted">Length</span>
                <button v-for="m in [15, 30, 60, 90]" :key="m" type="button" @click="setLength(m)"
                        class="rounded-full border px-2.5 py-0.5 text-xs font-semibold"
                        :class="minutes === m ? 'border-navy bg-navy text-white' : 'border-line text-ink-3 hover:bg-surface-raised'">{{ m < 60 ? `${m} min` : m === 60 ? '1 h' : '1.5 h' }}</button>
            </div>
            <label class="flex flex-col gap-1.5">
                <span class="text-[13px] font-semibold">Description <span class="font-normal text-muted">(optional)</span></span>
                <textarea v-model="form.description" rows="3" maxlength="5000" placeholder="What is the meeting about?" :class="field"></textarea>
            </label>
            <label class="flex flex-col gap-1.5">
                <span class="text-[13px] font-semibold">Invite attendees <span class="font-normal text-muted">(optional)</span></span>
                <input v-model="form.attendees" type="text" autocomplete="off" placeholder="jan@company.com, sarah@company.com" :class="field" />
                <span class="text-xs text-muted">Email addresses, separated by commas. They get the calendar invite.</span>
            </label>
            <p v-if="error" role="alert" class="rounded-lg bg-reserved-bg px-3 py-2 text-[13px] text-reserved-text">{{ error }}</p>
            <p class="text-xs text-muted">The booking goes into the room's calendar, the same as booking on the tablet at the door.</p>
        </form>
        <template #footer>
            <Button variant="secondary" @click="emit('close')">Cancel</Button>
            <Button type="submit" form="book-meeting" :disabled="busy">{{ busy ? 'Booking...' : 'Book' }}</Button>
        </template>
    </Modal>
</template>
