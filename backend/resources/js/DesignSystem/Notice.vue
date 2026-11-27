<script setup>
// Spacepad Design System: Notice. One component for telling someone something: row, banner or toast.
defineProps({
    title: { type: String, default: null },
    tone: { type: String, default: 'neutral' }, // danger | warning | success | info | neutral
    shape: { type: String, default: 'banner' }, // row | banner | toast
    tag: { type: String, default: null },
    action: { type: String, default: null },
    href: { type: String, default: null },
});
defineEmits(['action', 'close']);
</script>

<template>
    <div :class="['sp-notice', `sp-notice-${shape}`, `sp-notice-${tone}`]" :role="tone === 'danger' ? 'alert' : 'status'">
        <span v-if="tag && shape === 'row'" class="sp-notice-tag">{{ tag }}</span>
        <span class="sp-notice-text">
            <span v-if="title" class="sp-notice-title">{{ title }}</span>
            <span v-if="$slots.default" class="sp-notice-body"><slot /></span>
        </span>
        <a v-if="action && href" :href="href" class="sp-notice-action">{{ action }}</a>
        <button v-else-if="action" type="button" class="sp-notice-action" @click="$emit('action')">{{ action }}</button>
    </div>
</template>
