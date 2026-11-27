<script setup>
// The actions every display row shows inline: Details, Edit (names and location) and Customize
// (look and behaviour). Configuring a display is Pro, so on a free workspace Edit and Customize
// explain Pro instead. Anything else lives in the "more" menu passed in the slot.
import { Link } from '@inertiajs/vue3';
import { openUpgrade } from '../../../Components/Upgrade/upgrade';

const props = defineProps({
    configureUrl: { type: String, required: true },
    hasPro: Boolean,
    // On the overview Details is a link to the Displays page; there it opens the side panel.
    detailsHref: { type: String, default: null },
});
const emit = defineEmits(['details']);
const button = 'sp-btn sp-btn-secondary sp-btn-sm';
</script>

<template>
    <span class="flex items-center justify-end gap-1.5" @click.stop>
        <Link v-if="detailsHref" :href="detailsHref" :class="button">Details</Link>
        <button v-else type="button" :class="button" @click="emit('details')">Details</button>
        <template v-if="hasPro">
            <Link :href="`${props.configureUrl}#general`" :class="button">Edit</Link>
            <Link :href="`${props.configureUrl}#display`" :class="button">Customize</Link>
        </template>
        <template v-else>
            <button type="button" :class="button" @click="openUpgrade('configure')">Edit</button>
            <button type="button" :class="button" @click="openUpgrade('configure')">Customize</button>
        </template>
        <slot />
    </span>
</template>
