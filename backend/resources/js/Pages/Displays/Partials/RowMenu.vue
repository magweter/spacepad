<script setup>
// The "more" menu at the end of a display row: the same actions as the side panel. The menu is
// placed on the page body, so the table's scroll container does not cut it off; it opens upward
// when there is no room below.
import { nextTick, onBeforeUnmount, onMounted, ref } from 'vue';

defineProps({ display: { type: Object, required: true }, hasPro: Boolean });
const emit = defineEmits(['open', 'toggle', 'makeActive', 'diagnose', 'remove']);

const open = ref(false);
const button = ref(null);
const menu = ref(null);
const position = ref({});

async function toggle() {
    open.value = !open.value;
    if (!open.value) return;
    await nextTick();
    const r = button.value.getBoundingClientRect();
    const height = menu.value?.offsetHeight ?? 0;
    const below = window.innerHeight - r.bottom > height + 12;
    position.value = {
        right: `${window.innerWidth - r.right}px`,
        ...(below ? { top: `${r.bottom + 4}px` } : { bottom: `${window.innerHeight - r.top + 4}px` }),
    };
}

const close = () => { open.value = false; };
const pick = (event) => { close(); emit(event); };
const outside = (e) => {
    if (open.value && !button.value?.contains(e.target) && !menu.value?.contains(e.target)) close();
};
onMounted(() => {
    document.addEventListener('click', outside);
    window.addEventListener('scroll', close, true);
    window.addEventListener('resize', close);
});
onBeforeUnmount(() => {
    document.removeEventListener('click', outside);
    window.removeEventListener('scroll', close, true);
    window.removeEventListener('resize', close);
});

const item = 'flex w-full items-center gap-2 px-3 py-2 text-left text-[13px] hover:bg-surface-raised';
</script>

<template>
    <span class="flex justify-end">
        <button ref="button" type="button" :aria-label="`More actions for ${display.name}`" :aria-expanded="open" @click="toggle"
                class="inline-flex h-8 w-8 items-center justify-center rounded-md text-muted hover:bg-line-soft hover:text-ink">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="12" cy="5" r="2" /><circle cx="12" cy="12" r="2" /><circle cx="12" cy="19" r="2" /></svg>
        </button>
        <Teleport to="body">
            <span v-if="open" ref="menu" role="menu" :style="position"
                  class="fixed z-50 flex w-52 flex-col overflow-hidden rounded-[10px] border border-line bg-surface py-1 text-sm text-ink shadow-lg">
                <button type="button" role="menuitem" :class="item" @click="pick('open')">Details</button>
                <a v-if="hasPro" role="menuitem" :href="display.urls.configure" :class="item">Configure</a>
                <button v-if="display.status === 'requires_pro'" type="button" role="menuitem" :class="item" @click="pick('makeActive')">Make active</button>
                <button v-else type="button" role="menuitem" :class="item" @click="pick('toggle')">{{ display.status === 'active' ? 'Pause' : 'Resume' }}</button>
                <button v-if="hasPro" type="button" role="menuitem" :class="item" @click="pick('diagnose')">Troubleshoot sync</button>
                <button type="button" role="menuitem" :class="[item, 'text-reserved-text']" @click="pick('remove')">Delete</button>
            </span>
        </Teleport>
    </span>
</template>
