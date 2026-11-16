<script setup>
// A drawing of the tablet with the settings as they stand in the form, saved or not. It shows what
// the settings change (theme, texts, buttons, schedule, border, font, sizes, logo, background and
// the advertisement); the meeting in it is an example. Drawn at 640 by 400 and scaled to fit.
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import Segmented from './Segmented.vue';
import PreviewTimeline from './PreviewTimeline.vue';
import { defaultTexts } from './fields';

const props = defineProps({
    settings: { type: Object, required: true },
    room: { type: String, default: 'Meeting room' },
    subline: { type: String, default: '' },
    logo: { type: String, default: null },
    background: { type: String, default: null },
    advertisement: { type: String, default: null },
});

const state = ref('reserved');
const states = computed(() => [
    { value: 'available', label: 'Available' },
    { value: 'transitioning', label: 'Starting soon' },
    { value: 'reserved', label: 'Reserved' },
    ...(props.settings.check_in_enabled ? [{ value: 'checkin', label: 'Check-in' }] : []),
    ...(props.settings.advertisement_enabled && props.advertisement ? [{ value: 'advert', label: 'Advertisement' }] : []),
]);
// A state that disappears (check-in or the advertisement switched off) falls back to reserved.
const shown = computed(() => (states.value.some((s) => s.value === state.value) ? state.value : 'reserved'));

const s = computed(() => props.settings);
const text = (key) => (s.value[key] || '').trim() || defaultTexts[key];
const size = (element) => ({ small: 0.85, normal: 1, large: 1.2, xlarge: 1.4 }[s.value[`font_size_${element}`]] ?? 1);
const px = (value, element) => `${(value * (element ? size(element) : 1)).toFixed(1)}px`;

const tone = computed(() => ({ available: 'available', transitioning: 'checkin', checkin: 'checkin' }[shown.value] ?? 'reserved'));
const frameColor = computed(() => `var(--status-${tone.value})`);
const border = computed(() => ({ small: 4, medium: 7, large: 11 }[s.value.border_thickness] ?? 7));

// Glass: the dark ground with a glow in the status colour, the clock and title in the display
// face. Classic: black inside the status frame, everything in the chosen font.
const isGlass = computed(() => s.value.theme === 'glass');
const ground = computed(() => (isGlass.value
    ? 'radial-gradient(60% 80% at 100% 0%, color-mix(in srgb, var(--frame) 22%, transparent), transparent 70%), radial-gradient(70% 90% at 0% 100%, color-mix(in srgb, var(--frame) 30%, transparent), transparent 70%), var(--ground)'
    : '#000'));
const headline = computed(() => (isGlass.value ? 'font-display' : ''));

const title = computed(() => {
    switch (shown.value) {
        case 'available': return text('text_available');
        case 'transitioning': return text('text_transitioning');
        case 'checkin': return text('text_checkin');
        default: return s.value.show_meeting_title ? 'Q4 planning' : text('text_reserved');
    }
});
const detail = computed(() => ({
    available: 'Free until 11:00',
    transitioning: s.value.show_meeting_title ? 'Design sync starts in 4 min' : 'Next meeting starts in 4 min',
    checkin: s.value.show_meeting_title ? 'Q4 planning, 10:00 to 10:30' : '10:00 to 10:30',
}[shown.value] ?? 'Until 10:30, 42 min left'));
const organizer = computed(() => s.value.show_organizer && ['reserved', 'advert', 'checkin'].includes(shown.value));
const actions = computed(() => {
    if (shown.value === 'available' || shown.value === 'transitioning') return s.value.booking_enabled ? ['Book now'] : [];
    if (shown.value === 'checkin') return ['Check-in'];
    const list = [];
    if (s.value.extend_enabled) list.push('+15', '+30');
    // The example is a calendar meeting, so "tablet bookings only" hides the button too.
    if (s.value.cancel_permission === 'all') list.push('End now');
    return list;
});
const mode = computed(() => s.value.timeline_widget_mode || 'none'); // none | side_panel | inline | full_panel
const meetings = computed(() => [
    { title: s.value.show_meeting_title ? 'Q4 planning' : text('text_reserved'), from: 0.25, length: 0.125 },
    { title: s.value.show_meeting_title ? 'Design sync' : text('text_reserved'), from: 0.5, length: 0.25 },
]);

const clock = ref('');
const tick = () => { clock.value = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', hour12: false }); };
let timer;

const outer = ref(null);
const scale = ref(1);
let observer;
onMounted(() => {
    tick();
    timer = setInterval(tick, 20000);
    observer = new ResizeObserver(([entry]) => { scale.value = entry.contentRect.width / 640; });
    observer.observe(outer.value);
});
onBeforeUnmount(() => { clearInterval(timer); observer?.disconnect(); });

// The panels as the app draws them: frosted with a hairline edge in glass, a plain light lift in
// classic (FrostedPanel.defaultLift, 20% white, no edge).
const panel = computed(() => (isGlass.value
    ? 'border-[1.5px] border-[var(--glass-border)] bg-[var(--glass)] shadow-[inset_0_1px_0_var(--glass-border)]'
    : 'bg-white/20'));
// Classic panels are rounder and its buttons a touch lighter, as in the app.
const radius = computed(() => (isGlass.value ? 'rounded-[7px]' : 'rounded-[10px]'));
</script>

<template>
    <div class="flex flex-col gap-3.5">
        <div class="flex flex-wrap items-center gap-3">
            <h2 class="text-[15px] font-semibold">Live preview</h2>
            <span class="text-xs text-muted">Example meeting, your settings</span>
            <span class="flex-1"></span>
            <Segmented :model-value="shown" :options="states" label="Room state in the preview" @update:model-value="state = $event" />
        </div>

        <div class="rounded-[20px] bg-console-ground p-3.5 shadow-[var(--shadow-device),inset_0_0_0_1px_var(--brand-navy-700)]" :style="{ '--frame': frameColor }">
            <div ref="outer" class="relative aspect-[16/10] overflow-hidden rounded-[6px]">
                <!-- The screen. In side panel mode the schedule sits beside the frame, outside it, as in the app. -->
                <div data-theme="dark" class="absolute top-0 left-0 flex h-[400px] w-[640px] origin-top-left gap-3 text-[var(--text)]"
                     :style="{ transform: `scale(${scale})`, fontFamily: `'${s.font_family}', Inter, sans-serif`, background: isGlass ? 'var(--ground)' : '#000' }">
                    <div class="relative flex min-w-0 flex-1 overflow-hidden" :style="{ border: `${border}px solid var(--frame)`, background: ground }">
                        <img v-if="background" :src="background" alt="" class="absolute inset-0 h-full w-full object-cover opacity-45">

                        <div class="relative flex min-w-0 flex-1 flex-col px-[22px] pt-[18px] pb-4">
                            <!-- Header: clock, room name and, for the side panel, its calendar button -->
                            <div class="flex items-start justify-between gap-3">
                                <span :class="headline" class="leading-none font-bold tracking-tight tabular-nums" :style="{ fontSize: px(21, 'clock') }">{{ clock }}</span>
                                <span class="flex min-w-0 items-start gap-2 text-right">
                                    <span class="flex min-w-0 flex-col items-end">
                                        <span class="max-w-full leading-tight font-semibold tracking-tight [overflow-wrap:anywhere]" :style="{ fontSize: px(15, 'room_name') }">{{ room }}</span>
                                        <span v-if="subline" class="text-[var(--text-2)]" :style="{ fontSize: px(9, 'room_name') }">{{ subline }}</span>
                                    </span>
                                    <span v-if="mode === 'side_panel'" :class="[panel, radius]" class="flex h-[26px] w-[26px] flex-none items-center justify-center">
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2" /><path d="M16 2v4M8 2v4M3 10h18" /></svg>
                                    </span>
                                </span>
                            </div>

                            <!-- Middle: the status, with the inline schedule beside it -->
                            <div class="flex min-h-0 flex-1 gap-3 py-2">
                                <div class="flex min-w-0 flex-1 flex-col items-start justify-center gap-[5px]">
                                    <img v-if="logo" :src="logo" alt="" class="mb-2 h-[30px] w-auto max-w-[160px] object-contain">
                                    <span :class="headline" class="line-clamp-3 w-full leading-[1.02] font-bold tracking-tight [overflow-wrap:anywhere]" :style="{ fontSize: px(shown === 'advert' ? 26 : 34, 'title') }">{{ title }}</span>
                                    <span class="w-full text-[var(--text-muted)] [overflow-wrap:anywhere]" :style="{ fontSize: px(15, 'details') }">{{ detail }}</span>
                                    <span v-if="organizer" class="text-[var(--text-muted)]" :style="{ fontSize: px(13, 'details') }">Booked by Sanne de Vries</span>
                                    <span v-if="actions.length" class="mt-2.5 flex flex-wrap gap-1.5">
                                        <span v-for="a in actions" :key="a" :class="[panel, radius]" class="px-3 py-[7px] font-semibold" :style="{ fontSize: px(12, 'buttons') }">{{ a }}</span>
                                    </span>
                                </div>
                                <PreviewTimeline v-if="mode === 'inline' && shown !== 'advert'" class="h-[230px] w-[160px] flex-none self-center" :panel="panel" :radius="radius"
                                                 :font-size="px(10, 'calendar')" :meetings="meetings" />
                            </div>

                            <!-- Bottom bar -->
                            <div :class="[panel, radius]" class="flex h-[37px] items-center justify-between gap-3 px-3" :style="{ fontSize: px(11.5, 'toolbar') }">
                                <span class="min-w-0 truncate"><span class="mr-2.5 text-[var(--text-muted)] tabular-nums">11:00</span><b>{{ s.show_meeting_title ? 'Design sync' : text('text_reserved') }}</b></span>
                                <span v-if="s.view_schedule" class="inline-flex flex-none items-center gap-1.5">
                                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2" /><path d="M16 2v4M8 2v4M3 10h18" /></svg>View schedule
                                </span>
                            </div>
                        </div>

                        <!-- Full panel: the schedule takes the right side, top to bottom, inside the frame -->
                        <PreviewTimeline v-if="mode === 'full_panel' && shown !== 'advert'" class="relative my-[18px] mr-4 w-[160px] flex-none" :panel="panel" :radius="radius"
                                         :font-size="px(10, 'calendar')" :meetings="meetings" />

                        <div v-if="shown === 'advert'" class="relative w-1/2 flex-none">
                            <img :src="advertisement" alt="" class="h-full w-full object-cover">
                        </div>
                    </div>

                    <!-- Side panel: opened with the calendar button, beside the frame -->
                    <PreviewTimeline v-if="mode === 'side_panel' && shown !== 'advert'" class="w-[150px] flex-none" :panel="panel" :radius="radius"
                                     :font-size="px(10, 'calendar')" :meetings="meetings" />
                </div>
            </div>
        </div>
        <p v-if="mode === 'side_panel'" class="text-xs text-muted">The schedule opens beside the screen when someone taps the calendar button.</p>
    </div>
</template>
