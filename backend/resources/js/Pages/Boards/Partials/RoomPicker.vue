<script setup>
// Which rooms the board shows: every active display, or a chosen set drawn as removable chips.
import { computed, ref } from 'vue';
import Icon from '../../../Components/Icon.vue';
import Segmented from './Segmented.vue';

const props = defineProps({
    displays: { type: Array, required: true }, // [{ id, name }]
    showAll: { type: Boolean, default: true },
    selected: { type: Array, required: true }, // display ids, mutated in place
    error: { type: String, default: null },
});
const emit = defineEmits(['update:showAll']);

const adding = ref(false);
const known = computed(() => props.displays.filter((d) => props.selected.includes(d.id)));
const remaining = computed(() => props.displays.filter((d) => !props.selected.includes(d.id)));
const scope = computed({
    get: () => (props.showAll ? 'all' : 'chosen'),
    set: (v) => emit('update:showAll', v === 'all'),
});

function add(id) {
    props.selected.push(id);
    if (!remaining.value.length) adding.value = false;
}
function remove(id) {
    const i = props.selected.indexOf(id);
    if (i !== -1) props.selected.splice(i, 1);
}
</script>

<template>
    <div class="flex flex-col gap-2">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <span class="text-[13px] font-medium">Rooms on this board</span>
            <span class="text-xs text-muted">
                <template v-if="showAll">{{ displays.length }} of {{ displays.length }}</template>
                <template v-else>{{ known.length }} of {{ displays.length }}</template>
            </span>
        </div>
        <Segmented v-model="scope" label="Rooms on this board" :options="[{ value: 'all', label: 'All displays' }, { value: 'chosen', label: 'Choose rooms' }]" class="self-start" />

        <p v-if="!displays.length" class="text-xs text-muted">No active displays in this workspace yet. A display shows up here once it is set up.</p>
        <p v-else-if="showAll" class="text-xs text-muted">Every active display in this workspace, including the ones you add later.</p>
        <div v-else class="relative flex flex-wrap gap-2">
            <span v-for="d in known" :key="d.id" class="inline-flex items-center gap-1.5 rounded-lg border border-line bg-surface-raised py-[5px] pr-1.5 pl-3 text-xs font-medium">
                {{ d.name }}
                <button type="button" :aria-label="`Remove ${d.name}`" class="inline-flex h-[18px] w-[18px] items-center justify-center rounded bg-line text-ink-2 hover:bg-offline-border" @click="remove(d.id)">
                    <Icon name="close" :size="10" :stroke="3" />
                </button>
            </span>
            <button v-if="remaining.length" type="button" :aria-expanded="adding" class="inline-flex items-center gap-1.5 rounded-md border border-dashed border-offline-border px-3 py-[5px] text-xs font-semibold text-ink-2 hover:border-navy hover:text-navy" @click="adding = !adding">
                <Icon name="plus" :size="12" :stroke="2.5" />Add room
            </button>
            <div v-if="adding && remaining.length" class="absolute top-full left-0 z-20 mt-1.5 flex max-h-64 w-64 flex-col overflow-y-auto rounded-[10px] border border-line bg-surface py-1 shadow-lg">
                <button v-for="d in remaining" :key="d.id" type="button" class="px-3 py-2 text-left text-[13px] hover:bg-surface-raised" @click="add(d.id)">{{ d.name }}</button>
            </div>
        </div>
        <p v-if="!showAll && displays.length && !known.length" class="text-xs text-reserved-text">Add at least one room, or show all displays.</p>
        <p v-if="error" class="text-xs text-reserved-text">{{ error }}</p>
    </div>
</template>
