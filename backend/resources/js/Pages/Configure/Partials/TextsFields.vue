<script setup>
// State texts: the wording per room state. An empty field means the built-in text.
import FieldRow from './FieldRow.vue';
import { defaultTexts, inputClass } from './fields';

defineProps({
    form: { type: Object, required: true },
    fallback: { type: String, default: 'the built-in text' },
});

const fields = [
    { key: 'text_available', label: 'Available' },
    { key: 'text_reserved', label: 'Reserved' },
    { key: 'text_transitioning', label: 'About to change' },
    { key: 'text_checkin', label: 'Check-in' },
];
</script>

<template>
    <div class="flex flex-col gap-3">
        <FieldRow v-for="f in fields" :key="f.key" :label="f.label" :for="f.key" :error="form.errors[f.key]">
            <input :id="f.key" v-model="form[f.key]" type="text" maxlength="64" :placeholder="defaultTexts[f.key]" :class="inputClass">
        </FieldRow>
        <p class="text-xs text-muted">Leave a field empty to use {{ fallback }}.</p>
    </div>
</template>
