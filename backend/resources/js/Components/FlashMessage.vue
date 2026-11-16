<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import Notice from '../DesignSystem/Notice.vue';

defineProps({ success: { type: Boolean, default: true } });

const page = usePage();
const flash = computed(() => page.props.flash || {});
</script>

<template>
    <Notice v-if="flash.error" tone="danger" :title="flash.error" />
    <Notice v-else-if="flash.info" tone="info" :title="flash.info" />
    <Notice v-else-if="success && flash.success" tone="success" :title="flash.success" />
</template>
