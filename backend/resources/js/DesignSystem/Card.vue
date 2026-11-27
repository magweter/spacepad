<script setup>
// Spacepad Design System: Card. A 3px status rule on the left means "a room or device in this state".
defineProps({
    padding: { type: String, default: 'md' }, // none | sm | md | lg
    rule: { type: String, default: null },
    interactive: { type: Boolean, default: false },
});
</script>

<template>
    <div :class="['sp-card', padding !== 'none' ? `sp-card-pad-${padding}` : '', rule ? ['sp-card-rule', `sp-card-rule-${rule}`] : '', { 'sp-card-interactive': interactive }]">
        <slot />
    </div>
</template>
