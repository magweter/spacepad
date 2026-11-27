<script setup>
// Behavior: what people can do from the display. Binds straight onto the form it is given.
import SettingRow from '../../../DesignSystem/SettingRow.vue';
import Toggle from '../../../DesignSystem/Toggle.vue';
import FieldRow from './FieldRow.vue';
import { cancelOptions, inputClass, numberClass } from './fields';

defineProps({ form: { type: Object, required: true } });
</script>

<template>
    <div class="flex flex-col">
        <SettingRow label="Require check-in" help="Attendees confirm the meeting on the display. Without a check-in the room is released.">
            <Toggle v-model="form.check_in_enabled" label="Require check-in" />
        </SettingRow>
        <div v-if="form.check_in_enabled" class="grid grid-cols-1 gap-3.5 border-b border-line-soft py-3 sm:grid-cols-2">
            <div class="flex flex-col gap-1.5">
                <label for="check_in_minutes" class="text-[13px] font-medium">Check-in opens</label>
                <span class="flex items-center gap-2"><input id="check_in_minutes" v-model.number="form.check_in_minutes" type="number" min="1" max="60" :class="numberClass"><span class="text-[13px] text-muted">min before start</span></span>
                <span v-if="form.errors.check_in_minutes" class="text-xs font-medium text-reserved-text">{{ form.errors.check_in_minutes }}</span>
            </div>
            <div class="flex flex-col gap-1.5">
                <label for="check_in_grace_period" class="text-[13px] font-medium">Grace period</label>
                <span class="flex items-center gap-2"><input id="check_in_grace_period" v-model.number="form.check_in_grace_period" type="number" min="1" max="30" :class="numberClass"><span class="text-[13px] text-muted">min after start</span></span>
                <span v-if="form.errors.check_in_grace_period" class="text-xs font-medium text-reserved-text">{{ form.errors.check_in_grace_period }}</span>
            </div>
        </div>

        <SettingRow label="Book from the display" help="Bookings are written to the connected calendar, so the account needs write access.">
            <Toggle v-model="form.booking_enabled" label="Book from the display" />
        </SettingRow>
        <SettingRow v-if="form.booking_enabled" label="Book other days" help="Pick a date other than today on the tablet.">
            <Toggle v-model="form.allow_future_bookings" label="Book other days" />
        </SettingRow>
        <SettingRow label="Extend a meeting" help="Adds +15, +30 and +60 minute buttons during a meeting.">
            <Toggle v-model="form.extend_enabled" label="Extend a meeting" />
        </SettingRow>
        <SettingRow label="Hide admin actions" help="Hides switch room and log out. Long-press the room name to show them for 30 seconds.">
            <Toggle v-model="form.hide_admin_actions" label="Hide admin actions" />
        </SettingRow>

        <div class="pt-3">
            <FieldRow label="Who may end a meeting" for="cancel_permission" :error="form.errors.cancel_permission">
                <select id="cancel_permission" v-model="form.cancel_permission" :class="inputClass">
                    <option v-for="o in cancelOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
                </select>
            </FieldRow>
        </div>
    </div>
</template>
