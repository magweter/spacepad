<script setup>
// Live view: every room on a timeline of the day, or as cards. Today comes with the page and is
// reloaded every minute; another day is fetched from the calendars on request.
import { computed, onBeforeUnmount, onMounted, ref, toRef } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import PortalShell from '../../Components/PortalShell.vue';
import { useRoomDay } from '../../Composables/useRoomDay';
import { requestJson } from '../../Composables/http';
import RoomsNow from './Partials/RoomsNow.vue';

const props = defineProps({ shell: Object, rooms: Array, book: Boolean, urls: Object });

const roomsNow = ref(null);
const now = ref(new Date());

const day = ref(null);
const dayRooms = ref(null);
const loading = ref(false);
let request = 0;
const iso = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;

async function loadDay() {
    const ticket = ++request;
    loading.value = true;
    try {
        const data = await requestJson('GET', props.urls.day, { params: { date: iso(day.value), offset: -day.value.getTimezoneOffset() } });
        if (ticket === request) dayRooms.value = data.rooms;
    } catch {
        if (ticket === request) dayRooms.value = props.rooms.map((r) => ({ id: r.id, events: [], error: 'The day could not be loaded. Try again.' }));
    } finally {
        if (ticket === request) loading.value = false;
    }
}
function showDay(date) {
    const d = date ? new Date(date) : null;
    d?.setHours(0, 0, 0, 0);
    const today = new Date(); today.setHours(0, 0, 0, 0);
    if (!d || d.getTime() === today.getTime()) {
        request++;
        day.value = null; dayRooms.value = null; loading.value = false;
        return;
    }
    day.value = d;
    loadDay();
}
// After booking or changing a meeting: fetch the day shown again.
function refresh() {
    if (day.value) return loadDay();
    loading.value = true;
    router.reload({ only: ['rooms'], onSuccess: () => { now.value = new Date(); }, onFinish: () => { loading.value = false; } });
}

const timelineRooms = computed(() => {
    if (!day.value || !dayRooms.value) return day.value ? props.rooms.map((r) => ({ ...r, events: [] })) : props.rooms;
    const byId = Object.fromEntries(dayRooms.value.map((r) => [r.id, r]));
    return props.rooms.map((r) => {
        const d = byId[r.id];
        return { ...r, events: d?.events ?? [], error: d?.error ?? null, hasData: !!d && d.events !== null && !d.error };
    });
});
const timeline = useRoomDay(timelineRooms, now, { day, fullDay: true });

// Live enough: the clock moves every 20 seconds, the rooms are fetched again every minute.
let tick;
let poll;
onMounted(() => {
    tick = setInterval(() => { now.value = new Date(); }, 20000);
    poll = setInterval(() => { if (!day.value) router.reload({ only: ['rooms'], onSuccess: () => { now.value = new Date(); } }); }, 60000);
    // Opened with "Book a room" from the overview.
    if (props.book && roomsNow.value?.canBook) roomsNow.value.book();
});
onBeforeUnmount(() => { clearInterval(tick); clearInterval(poll); });
</script>

<template>
    <Head title="Live view" />

    <PortalShell :shell="shell" title="Live view" subtitle="Every room as it is right now, refreshed every minute.">
        <div class="flex flex-col gap-6 px-6 pt-6 pb-10 md:px-10">
            <RoomsNow ref="roomsNow" :rooms="timeline.list.value" :hours="timeline.hours.value" :now-left="timeline.nowLeft.value" :now-label="timeline.nowLabel.value"
                      :day="timeline.dayStart.value" :is-today="timeline.isToday.value" :loading="loading" :percent="timeline.percent" :time-at="timeline.timeAt"
                      :now="now" :urls="urls" @day="showDay" @changed="refresh" />
        </div>
    </PortalShell>
</template>
