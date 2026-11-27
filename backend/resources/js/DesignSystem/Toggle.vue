<script setup>
// Spacepad Design System: switch. Use with v-model.
defineProps({ modelValue: Boolean, label: { type: String, required: true }, disabled: Boolean });
defineEmits(['update:modelValue']);
</script>

<template>
    <button type="button" role="switch" class="sp-switch disabled:cursor-not-allowed disabled:opacity-50" :aria-checked="modelValue ? 'true' : 'false'" :aria-label="label"
            :disabled="disabled" @click="$emit('update:modelValue', !modelValue)"><span class="sp-switch-knob"></span></button>
</template>
