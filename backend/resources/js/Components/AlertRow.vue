<script setup>
// One alert set by an admin, drawn as a "Needs attention" row with its type as the tag. Used on the
// overview, in the one-time popup and in the admin preview.
import { computed } from 'vue';
import { toneOf } from './alertTones.js';
import AttentionRow from './Attention/AttentionRow.vue';

const props = defineProps({
    tone: { type: String, default: 'attention' }, // a key of alertTones
    title: { type: String, default: '' },
    body: { type: String, default: null },
    standalone: { type: Boolean, default: false },
    dismissible: { type: Boolean, default: false },
});
defineEmits(['dismiss']);

const t = computed(() => toneOf(props.tone));
</script>

<template>
    <AttentionRow :tone="t.chip" :tag="t.label" :title="title || 'Your title'" :body="body" :standalone="standalone" :dismissible="dismissible" @dismiss="$emit('dismiss')" />
</template>
