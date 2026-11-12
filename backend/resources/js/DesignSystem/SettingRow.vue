<script setup>
// Spacepad Design System: SettingRow. Label and help on the left, one control on the right.
defineProps({ label: { type: String, required: true }, help: { type: String, default: null } });
</script>

<template>
    <div class="sp-setting">
        <span class="sp-setting-text">
            <span class="sp-setting-label">{{ label }}</span>
            <span v-if="help || $slots.help" class="sp-setting-help"><slot name="help">{{ help }}</slot></span>
        </span>
        <span class="sp-setting-control"><slot /></span>
    </div>
</template>
