<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import StepFooter from '../../../Components/StepFooter.vue';

defineOptions({ inheritAttrs: false });

const props = defineProps({
    accounts: Array, display: Object, canCreateDisplay: Boolean, urls: Object, next: String, previous: String, onboarding: Object,
});
defineEmits(['skip-setup']);

const accountId = ref(props.accounts[0]?.id ?? null);
const account = computed(() => props.accounts.find((a) => a.id === accountId.value) ?? null);
const loading = ref(false);
const error = ref(null);
const rooms = ref([]);
const calendars = ref([]);
const search = ref('');
const picked = ref(null);

const form = useForm({
    name: '', displayName: '', account: '', provider: '', room: null, calendar: null, from_onboarding: 1,
});

// Room calendars when the account has them, otherwise ordinary calendars.
const usingRooms = computed(() => rooms.value.length > 0);
const items = computed(() => {
    const list = usingRooms.value ? rooms.value : calendars.value;
    const q = search.value.trim().toLowerCase();
    return q ? list.filter((i) => `${i.name} ${i.detail ?? ''}`.toLowerCase().includes(q)) : list;
});

async function load() {
    if (!account.value) return;
    loading.value = true;
    error.value = null;
    picked.value = null;
    const params = new URLSearchParams({ provider: account.value.provider, account: account.value.id });
    try {
        const response = await fetch(`${props.urls.resources}?${params}`, { headers: { Accept: 'application/json' } });
        const data = await response.json();
        rooms.value = data.rooms ?? [];
        calendars.value = data.calendars ?? [];
        error.value = data.error;
    } catch {
        error.value = 'We could not load your rooms and calendars. Try again.';
    } finally {
        loading.value = false;
    }
}

function pick(item) {
    picked.value = item;
    form.displayName = item.name;
}

function submit() {
    if (!picked.value || !account.value) return;
    form.transform((data) => ({
        ...data,
        name: data.displayName || picked.value.name,
        account: account.value.id,
        provider: account.value.provider,
        room: usingRooms.value ? `${picked.value.id},${picked.value.name}` : null,
        calendar: usingRooms.value ? null : `${picked.value.id},${picked.value.name}`,
    })).post(props.urls.displaysStore, { preserveScroll: true });
}

watch(accountId, load);
onMounted(() => {
    if (!props.display) load();
});
</script>

<template>
    <div class="flex flex-col gap-2">
        <h1 class="text-[28px] leading-tight font-bold tracking-tight">{{ display ? 'Your first display is ready' : 'Which room goes on your first display?' }}</h1>
        <p class="text-muted">{{ display ? 'Next, put it on a tablet.' : 'Pick one room. Add the rest later, once this one is on the wall.' }}</p>
    </div>

    <div v-if="display" class="rounded-xl border border-line bg-white p-4">
        <span class="font-semibold">{{ display.name }}</span>
    </div>

    <div v-else-if="!accounts.length" class="rounded-xl border border-line bg-white p-5 text-[13px] text-muted">
        Connect a calendar first, then pick the room here.
    </div>

    <div v-else-if="!canCreateDisplay" class="rounded-xl border border-line bg-white p-5 text-[13px] text-muted">
        This workspace already has its free display. Upgrade to Pro to add more rooms.
    </div>

    <div v-else class="flex flex-col gap-4">
        <label v-if="accounts.length > 1" class="flex flex-col gap-1.5">
            <span class="text-[13px] font-medium">Account</span>
            <select v-model="accountId" class="rounded-[10px] border border-slate-300 bg-white px-3.5 py-2.5 text-sm focus:border-navy focus:outline-none">
                <option v-for="a in accounts" :key="a.id" :value="a.id">{{ a.providerLabel }} · {{ a.email || a.name }}</option>
            </select>
        </label>

        <div class="flex flex-col overflow-hidden rounded-xl border border-line bg-white">
            <div class="border-b border-line p-3">
                <input v-model="search" type="search" :placeholder="usingRooms ? 'Search rooms' : 'Search calendars'"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-navy focus:outline-none">
            </div>
            <p v-if="loading" class="p-4 text-[13px] text-muted">Loading rooms…</p>
            <p v-else-if="error" class="p-4 text-[13px] text-red-700">{{ error }} <button type="button" class="font-semibold underline" @click="load">Try again</button></p>
            <p v-else-if="!items.length" class="p-4 text-[13px] text-muted">Nothing found in this account.</p>
            <ul v-else class="max-h-80 divide-y divide-line-soft overflow-y-auto" role="listbox">
                <li v-for="item in items" :key="item.id">
                    <button type="button" role="option" :aria-selected="picked?.id === item.id" @click="pick(item)"
                            class="flex w-full items-center gap-3 px-4 py-3 text-left hover:bg-surface-raised"
                            :class="picked?.id === item.id ? 'bg-surface-raised' : ''">
                        <span class="flex h-4 w-4 flex-none items-center justify-center rounded-full border-2" :class="picked?.id === item.id ? 'border-navy bg-navy' : 'border-slate-300'">
                            <span v-if="picked?.id === item.id" class="h-1.5 w-1.5 rounded-full bg-white"></span>
                        </span>
                        <span class="flex min-w-0 flex-col">
                            <span class="truncate font-medium">{{ item.name }}</span>
                            <span v-if="item.detail" class="truncate text-xs text-muted">{{ item.detail }}</span>
                        </span>
                    </button>
                </li>
            </ul>
            <p v-if="!loading && !usingRooms && calendars.length" class="border-t border-line-soft px-4 py-2.5 text-xs text-muted">
                No room calendars found, so these are your own calendars.
            </p>
        </div>

        <label v-if="picked" class="flex flex-col gap-1.5">
            <span class="text-[13px] font-medium">Name on the tablet</span>
            <input v-model="form.displayName" type="text" required
                   class="max-w-sm rounded-[10px] border border-slate-300 bg-white px-3.5 py-2.5 text-sm focus:border-navy focus:outline-none">
        </label>
        <span v-for="(message, field) in form.errors" :key="field" class="text-xs text-red-700">{{ message }}</span>
    </div>

    <StepFooter :previous="previous" :show-skip="!display" skip-label="Do this later"
                :continue-label="display ? 'Pair a tablet' : (picked ? `Create display for ${form.displayName || picked.name}` : 'Create display')"
                :continue-disabled="!display && !picked" :processing="form.processing"
                @back="onboarding.goTo(previous)" @skip="onboarding.skipAndGo('room', 'ready')"
                @skip-setup="$emit('skip-setup')" @continue="display ? onboarding.completeAndGo('room', 'tablet') : submit()" />
</template>
