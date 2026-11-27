<script setup>
// Pick a workspace by searching for it: its name, a member's name or email, or its ULID. Every
// result shows the owner's email, since workspace names alone are often unclear. The chosen
// workspace's ULID is the value, so the form posts what it always did.
import { onBeforeUnmount, ref, watch } from 'vue';
import Icon from '../../../Components/Icon.vue';
import { requestJson } from '../../../Composables/http';

const props = defineProps({
    modelValue: { type: String, default: '' },
    id: { type: String, required: true },
    label: { type: String, required: true },
    lookupUrl: { type: String, required: true },
    initial: { type: Object, default: null }, // { id, name, owner? } from a preview or a ?source= link
    exclude: { type: String, default: null }, // the workspace picked on the other side
    error: { type: String, default: null },
});
const emit = defineEmits(['update:modelValue']);

const chosen = ref(props.initial?.id === props.modelValue ? props.initial : null);
const query = ref('');
const results = ref([]);
const open = ref(false);
const loading = ref(false);
const active = ref(0);
let timer;
let ticket = 0;

watch(query, (q) => {
    clearTimeout(timer);
    if (q.trim().length < 2) { results.value = []; loading.value = false; return; }
    loading.value = true;
    timer = setTimeout(async () => {
        const mine = ++ticket;
        try {
            const data = await requestJson('GET', props.lookupUrl, { params: { q: q.trim() } });
            if (mine !== ticket) return;
            results.value = data.workspaces.filter((w) => w.id !== props.exclude);
            active.value = 0;
            open.value = true;
        } catch {
            if (mine === ticket) results.value = [];
        } finally {
            if (mine === ticket) loading.value = false;
        }
    }, 250);
});
onBeforeUnmount(() => clearTimeout(timer));

// Closed a moment after the field loses focus, so a click on a result still lands.
const closeSoon = () => setTimeout(() => { open.value = false; }, 150);

function pick(workspace) {
    chosen.value = workspace;
    emit('update:modelValue', workspace.id);
    open.value = false;
    query.value = '';
}
function change() {
    chosen.value = null;
    emit('update:modelValue', '');
}
function onKey(e) {
    if (!open.value || !results.value.length) return;
    if (e.key === 'ArrowDown') { e.preventDefault(); active.value = (active.value + 1) % results.value.length; }
    if (e.key === 'ArrowUp') { e.preventDefault(); active.value = (active.value - 1 + results.value.length) % results.value.length; }
    if (e.key === 'Enter') { e.preventDefault(); pick(results.value[active.value]); }
    if (e.key === 'Escape') open.value = false;
}
const meta = (w) => [w.owner || 'No owner', `${w.members} ${w.members === 1 ? 'member' : 'members'}`, `${w.displays} ${w.displays === 1 ? 'display' : 'displays'}`].join(', ');
</script>

<template>
    <div class="flex flex-col gap-1.5">
        <label :for="id" class="text-[13px] font-medium text-ink">{{ label }}</label>

        <div v-if="chosen" class="flex items-center gap-3 rounded-md border border-line bg-surface px-3 py-2">
            <span class="flex min-w-0 flex-1 flex-col">
                <span class="truncate text-[13px] font-semibold text-navy">{{ chosen.name }}</span>
                <span v-if="chosen.owner" class="truncate text-xs text-muted">{{ chosen.owner }}</span>
                <span class="truncate font-mono text-[11px] text-muted">{{ chosen.id }}</span>
            </span>
            <button type="button" class="sp-btn sp-btn-secondary sp-btn-sm" @click="change">Change</button>
        </div>

        <div v-else class="relative">
            <Icon name="search" :size="15" class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-muted" />
            <input :id="id" v-model="query" type="search" autocomplete="off" placeholder="Workspace name, member email or ULID"
                   role="combobox" :aria-expanded="open" :aria-controls="`${id}-results`" :aria-invalid="!!error"
                   class="w-full rounded-md border bg-surface py-2 pr-3 pl-9 text-[13px] text-ink focus:outline-none"
                   :class="error ? 'border-reserved focus:border-reserved' : 'border-line focus:border-navy'"
                   @focus="open = results.length > 0" @blur="closeSoon" @keydown="onKey">
            <span v-if="loading" class="absolute top-1/2 right-3 h-3.5 w-3.5 -translate-y-1/2 animate-spin rounded-full border-2 border-line border-t-navy" aria-hidden="true"></span>
            <ul v-if="open" :id="`${id}-results`" role="listbox"
                class="absolute inset-x-0 top-full z-20 mt-1 max-h-72 overflow-y-auto rounded-md border border-line bg-surface py-1 shadow-lg">
                <li v-for="(w, i) in results" :key="w.id" role="option" :aria-selected="i === active"
                    class="flex cursor-pointer flex-col px-3 py-2" :class="i === active ? 'bg-ground' : ''"
                    @mousedown.prevent="pick(w)" @mouseenter="active = i">
                    <span class="truncate text-[13px] font-semibold text-navy">{{ w.name }}</span>
                    <span class="truncate text-xs text-muted">{{ meta(w) }}</span>
                </li>
                <li v-if="!results.length" class="px-3 py-2 text-[13px] text-muted">No workspace found</li>
            </ul>
        </div>

        <p v-if="error" class="text-xs text-reserved-text">{{ error }}</p>
    </div>
</template>
