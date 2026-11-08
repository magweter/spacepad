<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage();
const flash = computed(() => page.props.flash || {});
</script>

<template>
    <div v-if="flash.error" role="alert" class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ flash.error }}</div>
    <div v-else-if="flash.info" role="status" class="rounded-xl border border-line bg-info-bg px-4 py-3 text-sm text-info">{{ flash.info }}</div>
</template>
