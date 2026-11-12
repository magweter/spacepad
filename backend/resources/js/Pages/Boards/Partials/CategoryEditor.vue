<script setup>
// Room categories: sections of the board, for example per floor. Rooms are dragged between the
// sections, or moved with the select on each chip. Rooms left out are shown last, under "Other".
// The categories array belongs to the form and is changed in place.
import { computed, ref } from 'vue';
import Icon from '../../../Components/Icon.vue';

const props = defineProps({
    categories: { type: Array, required: true }, // [{ name, display_ids }]
    displays: { type: Array, required: true }, // [{ id, name }]
    onBoard: { type: Array, default: null }, // null: every display is on the board
});

const dragging = ref(null);

const label = (id) => props.displays.find((d) => d.id === id)?.name ?? id;
const isOnBoard = (id) => props.onBoard === null || props.onBoard.includes(id);
const ungrouped = computed(() => {
    const assigned = new Set(props.categories.flatMap((c) => c.display_ids));
    return props.displays.filter((d) => !assigned.has(d.id)).map((d) => d.id);
});

const add = () => props.categories.push({ name: '', display_ids: [] });
const remove = (i) => props.categories.splice(i, 1);
function move(i, dir) {
    const t = i + dir;
    if (t < 0 || t >= props.categories.length) return;
    const [c] = props.categories.splice(i, 1);
    props.categories.splice(t, 0, c);
}
function detach(id) {
    props.categories.forEach((c) => {
        const i = c.display_ids.indexOf(id);
        if (i !== -1) c.display_ids.splice(i, 1);
    });
}
function moveTo(id, target) {
    if (target === '') return;
    detach(id);
    if (target !== 'none') props.categories[Number(target)]?.display_ids.push(id);
}
function dropInto(index) {
    const id = dragging.value;
    dragging.value = null;
    if (!id) return;
    detach(id);
    if (index !== null) props.categories[index]?.display_ids.push(id);
}
// Dropped on another room: insert before it, so rooms can be ordered inside a category.
function dropBefore(index, before) {
    const id = dragging.value;
    dragging.value = null;
    if (!id || id === before) return;
    detach(id);
    if (index === null) return;
    const ids = props.categories[index]?.display_ids;
    if (!ids) return;
    const at = ids.indexOf(before);
    ids.splice(at === -1 ? ids.length : at, 0, id);
}
</script>

<template>
    <div class="flex flex-col gap-3">
        <div v-for="(category, ci) in categories" :key="ci" class="rounded-[10px] border border-line" @dragover.prevent @drop.prevent="dropInto(ci)">
            <div class="flex items-center gap-1.5 border-b border-line-soft bg-surface-raised px-3 py-2">
                <input v-model="category.name" type="text" maxlength="64" placeholder="Category name, for example Floor 1" aria-label="Category name"
                       class="min-w-0 flex-1 rounded-lg border border-line bg-surface px-2.5 py-1.5 text-[13px] font-medium focus:border-navy focus:outline-none">
                <button type="button" :disabled="ci === 0" aria-label="Move category up" title="Move category up" class="rounded-md px-2 py-1 text-muted hover:bg-line disabled:opacity-30" @click="move(ci, -1)">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m18 15-6-6-6 6" /></svg>
                </button>
                <button type="button" :disabled="ci === categories.length - 1" aria-label="Move category down" title="Move category down" class="rounded-md px-2 py-1 text-muted hover:bg-line disabled:opacity-30" @click="move(ci, 1)">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6" /></svg>
                </button>
                <button type="button" aria-label="Remove category" title="Remove category" class="rounded-md px-2 py-1 text-reserved-text hover:bg-reserved-bg" @click="remove(ci)">
                    <Icon name="close" :size="14" />
                </button>
            </div>
            <div class="flex flex-wrap items-center gap-2 p-3">
                <span v-for="id in category.display_ids" :key="id" draggable="true"
                      class="inline-flex cursor-move items-center gap-1.5 rounded-full border border-line py-1 pr-1.5 pl-3 text-xs font-medium"
                      :class="[dragging === id ? 'opacity-50' : '', isOnBoard(id) ? 'bg-surface' : 'bg-surface-raised text-muted']"
                      @dragstart="dragging = id" @dragend="dragging = null" @dragover.prevent @drop.prevent.stop="dropBefore(ci, id)">
                    {{ label(id) }}<span v-if="!isOnBoard(id)" class="text-[11px] italic">not on this board</span>
                    <select aria-label="Move this room to another category" title="Move this room to another category" class="rounded-md border border-line bg-surface px-1 py-0.5 text-[11px] text-muted"
                            @change="moveTo(id, $event.target.value); $event.target.value = ''">
                        <option value="">Move</option>
                        <option v-for="(c, i) in categories" :key="i" :value="i" :disabled="i === ci">{{ c.name || 'Unnamed category' }}</option>
                        <option value="none">Other</option>
                    </select>
                </span>
                <span v-if="!category.display_ids.length" class="text-xs text-muted">Drag rooms here, or use Move on a room below.</span>
            </div>
        </div>

        <div class="rounded-[10px] border border-dashed border-offline-border" @dragover.prevent @drop.prevent="dropInto(null)">
            <div class="border-b border-line-soft px-3 py-2 text-[13px] font-medium text-muted">Other (ungrouped)</div>
            <div class="flex flex-wrap items-center gap-2 p-3">
                <span v-for="id in ungrouped" :key="id" draggable="true"
                      class="inline-flex cursor-move items-center gap-1.5 rounded-full border border-line py-1 pr-1.5 pl-3 text-xs font-medium"
                      :class="[dragging === id ? 'opacity-50' : '', isOnBoard(id) ? 'bg-surface' : 'bg-surface-raised text-muted']"
                      @dragstart="dragging = id" @dragend="dragging = null">
                    {{ label(id) }}<span v-if="!isOnBoard(id)" class="text-[11px] italic">not on this board</span>
                    <select v-if="categories.length" aria-label="Move this room to a category" title="Move this room to a category" class="rounded-md border border-line bg-surface px-1 py-0.5 text-[11px] text-muted"
                            @change="moveTo(id, $event.target.value); $event.target.value = ''">
                        <option value="">Move</option>
                        <option v-for="(c, i) in categories" :key="i" :value="i">{{ c.name || 'Unnamed category' }}</option>
                    </select>
                </span>
                <span v-if="!ungrouped.length" class="text-xs text-muted">Every room is in a category.</span>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <button type="button" class="inline-flex items-center gap-1.5 rounded-full border border-dashed border-offline-border px-3 py-[5px] text-xs font-semibold text-ink-2 hover:border-navy hover:text-navy" @click="add">
                <Icon name="plus" :size="12" :stroke="2.5" />Add category
            </button>
            <span class="text-xs text-muted">A category without a name is dropped when you save. Empty categories are kept but not shown.</span>
        </div>
    </div>
</template>
