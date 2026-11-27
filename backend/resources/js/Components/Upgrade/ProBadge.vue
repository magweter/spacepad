<script setup>
// The Pro label. In a workspace without Pro it is a button that opens the upgrade dialog on the
// feature it sits next to; with Pro it is only a label.
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { openUpgrade } from './upgrade';

const props = defineProps({ feature: { type: String, default: 'displays' } });
const page = usePage();
const clickable = computed(() => page.props.upgrade && !page.props.upgrade.hasPro);
const classes = 'rounded-md border border-info-border bg-info-bg px-1.5 py-px font-sans text-[11px] font-semibold tracking-normal text-info';
</script>

<template>
    <button v-if="clickable" type="button" :class="[classes, 'cursor-pointer hover:border-info hover:bg-surface']" title="See what Pro adds"
            @click.prevent.stop="openUpgrade(props.feature)">Pro</button>
    <span v-else :class="classes">Pro</span>
</template>
