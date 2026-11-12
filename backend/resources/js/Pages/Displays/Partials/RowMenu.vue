<script setup>
// The "more" menu at the end of a display row: the same actions as the side panel.
import { onBeforeUnmount, onMounted, ref } from 'vue';

defineProps({ display: { type: Object, required: true }, hasPro: Boolean });
const emit = defineEmits(['open', 'toggle', 'makeActive', 'diagnose', 'remove']);

const open = ref(false);
const root = ref(null);
const pick = (event) => { open.value = false; emit(event); };
const outside = (e) => { if (open.value && root.value && !root.value.contains(e.target)) open.value = false; };
onMounted(() => document.addEventListener('click', outside));
onBeforeUnmount(() => document.removeEventListener('click', outside));

const item = 'flex w-full items-center gap-2 px-3 py-2 text-left text-[13px] hover:bg-surface-raised';
</script>

<template>
    <span ref="root" class="relative flex justify-end">
        <button type="button" :aria-label="`More actions for ${display.name}`" :aria-expanded="open" @click="open = !open"
                class="inline-flex h-8 w-8 items-center justify-center rounded-md text-muted hover:bg-line-soft hover:text-ink">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="12" cy="5" r="2" /><circle cx="12" cy="12" r="2" /><circle cx="12" cy="19" r="2" /></svg>
        </button>
        <span v-if="open" role="menu" class="absolute top-full right-0 z-20 mt-1 flex w-52 flex-col overflow-hidden rounded-[10px] border border-line bg-surface py-1 text-ink shadow-lg">
            <button type="button" role="menuitem" :class="item" @click="pick('open')">Details</button>
            <a v-if="hasPro" role="menuitem" :href="display.urls.configure" :class="item">Configure</a>
            <button v-if="display.status === 'requires_pro'" type="button" role="menuitem" :class="item" @click="pick('makeActive')">Make active</button>
            <button v-else type="button" role="menuitem" :class="item" @click="pick('toggle')">{{ display.status === 'active' ? 'Pause' : 'Resume' }}</button>
            <button v-if="hasPro" type="button" role="menuitem" :class="item" @click="pick('diagnose')">Troubleshoot sync</button>
            <button type="button" role="menuitem" :class="[item, 'text-reserved-text']" @click="pick('remove')">Delete</button>
        </span>
    </span>
</template>
