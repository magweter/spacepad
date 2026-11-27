<script setup>
// Display: what the screen shows, from the schedule to the meeting details.
import SettingRow from '../../../DesignSystem/SettingRow.vue';
import Toggle from '../../../DesignSystem/Toggle.vue';
import Segmented from './Segmented.vue';
import FieldRow from './FieldRow.vue';
import { borderOptions, inputClass, timelineOptions } from './fields';

defineProps({ form: { type: Object, required: true } });
const timelineHelp = (value) => timelineOptions.find((o) => o.value === value)?.help;
</script>

<template>
    <div class="flex flex-col">
        <div class="flex flex-col gap-3 border-b border-line-soft pb-3">
            <FieldRow label="Today's schedule" for="timeline_widget_mode" :help="timelineHelp(form.timeline_widget_mode)" :error="form.errors.timeline_widget_mode">
                <select id="timeline_widget_mode" v-model="form.timeline_widget_mode" :class="inputClass">
                    <option v-for="o in timelineOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
                </select>
            </FieldRow>
            <FieldRow label="Status border" help="The coloured frame around the screen. Large reads best from a corridor.">
                <Segmented v-model="form.border_thickness" :options="borderOptions" label="Status border" />
            </FieldRow>
        </div>

        <SettingRow label="View schedule button" help="A button in the bottom bar that opens today's full schedule.">
            <Toggle v-model="form.view_schedule" label="View schedule button" />
        </SettingRow>
        <SettingRow label="Show meeting titles" help="When off, the reserved text shows instead. Useful when subjects are confidential.">
            <Toggle v-model="form.show_meeting_title" label="Show meeting titles" />
        </SettingRow>
        <SettingRow label="Show the organizer" help="Who booked the meeting, from the calendar event.">
            <Toggle v-model="form.show_organizer" label="Show the organizer" />
        </SettingRow>
        <SettingRow label="Show the meeting location" help="The event's location line in the day schedule. Often a full street address.">
            <Toggle v-model="form.show_meeting_location" label="Show the meeting location" />
        </SettingRow>
        <SettingRow label="All-day events block the room" help="Turn off when the calendar holds all-day notes that are not bookings.">
            <Toggle v-model="form.all_day_events_block" label="All-day events block the room" />
        </SettingRow>
        <SettingRow label="LED strips follow the room state" help="Philips signage displays with SICP only. No effect on other devices.">
            <Toggle v-model="form.led_enabled" label="LED strips follow the room state" />
        </SettingRow>
    </div>
</template>
