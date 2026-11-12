<script setup>
import { Head } from '@inertiajs/vue3';
import PortalShell from '../../Components/PortalShell.vue';

defineProps({ shell: Object });
</script>

<template>
    <Head title="Settings" />
    <PortalShell :shell="shell" title="Settings">
        <div class="px-6 pt-7 pb-10 md:px-8"></div>
    </PortalShell>
</template>
