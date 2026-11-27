<script setup>
// Spacepad Design System: EmptyState. One sentence, one action, no illustration.
defineProps({ title: { type: String, required: true } });
</script>

<template>
    <div class="sp-empty">
        <span class="sp-empty-title">{{ title }}</span>
        <span v-if="$slots.default" class="sp-empty-body"><slot /></span>
        <div v-if="$slots.actions" class="sp-empty-actions"><slot name="actions" /></div>
    </div>
</template>
