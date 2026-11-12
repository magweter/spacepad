<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import StepHeading from '../../../Components/StepHeading.vue';
import Button from '../../../DesignSystem/Button.vue';
import Card from '../../../DesignSystem/Card.vue';
import EmptyState from '../../../DesignSystem/EmptyState.vue';

defineOptions({ inheritAttrs: false });

const props = defineProps({ accounts: Array, display: Object, canCreateDisplay: Boolean, urls: Object, eyebrow: String, flow: Object });

const accountId = ref(props.accounts[0]?.id ?? null);
const account = computed(() => props.accounts.find((a) => a.id === accountId.value) ?? null);
const loading = ref(false);
const error = ref(null);
const rooms = ref([]);
const calendars = ref([]);
const search = ref('');
const picked = ref(null);

const form = useForm({ name: '', displayName: '', account: '', provider: '', from_onboarding: 1 });

// Room calendars when the account has them, otherwise the account's own calendars.
const usingRooms = computed(() => rooms.value.length > 0);
const items = computed(() => {
    const list = usingRooms.value ? rooms.value : calendars.value;
    const q = search.value.trim().toLowerCase();
    return q ? list.filter((i) => `${i.name} ${i.detail ?? ''}`.toLowerCase().includes(q)) : list;
});
const intro = computed(() => {
    if (!account.value) return '';
    const count = (usingRooms.value ? rooms.value : calendars.value).length;
    return usingRooms.value
        ? `We found ${count} room ${count === 1 ? 'calendar' : 'calendars'} in ${account.value.providerLabel}.`
        : `No room calendars in ${account.value.providerLabel}, so these are your own calendars. Pick the one this display shows.`;
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
        // Only the key that applies: the store rules expect a string when a key is sent.
        [usingRooms.value ? 'room' : 'calendar']: `${picked.value.id},${picked.value.name}`,
    })).post(props.urls.displaysStore, { preserveScroll: true });
}

watch(accountId, load);
onMounted(() => {
    if (!props.display && props.canCreateDisplay) load();
});
</script>

<template>
    <template v-if="display">
        <StepHeading :eyebrow="eyebrow" title="The display is created">Next, put it on a tablet.</StepHeading>
        <Card rule="available"><span class="font-semibold">{{ display.name }}</span></Card>
        <div class="flex justify-end"><Button size="lg" @click="flow.completeAndGo('room', 'tablet')">Pair a tablet</Button></div>
    </template>

    <template v-else>
        <StepHeading :eyebrow="eyebrow" title="Which room goes on this display?">{{ intro }}</StepHeading>

        <EmptyState v-if="!accounts.length" title="Connect a calendar first">The room list comes from your calendar.
            <template #actions><Button variant="secondary" @click="flow.goTo('calendar')">Connect a calendar</Button></template>
        </EmptyState>

        <EmptyState v-else-if="!canCreateDisplay" title="This workspace already has its free display">More displays need Pro.</EmptyState>

        <div v-else class="flex flex-col gap-4">
            <label v-if="accounts.length > 1" class="flex flex-col gap-1.5">
                <span class="text-[13px] font-semibold">Account</span>
                <select v-model="accountId" class="rounded-[10px] border border-offline-border bg-surface px-3.5 py-[11px] text-sm focus:border-navy focus:outline-none">
                    <option v-for="a in accounts" :key="a.id" :value="a.id">{{ a.providerLabel }}, {{ a.email || a.name }}</option>
                </select>
            </label>

            <div class="flex flex-col overflow-hidden rounded-lg border border-line bg-surface">
                <div class="border-b border-line-soft p-3">
                    <input v-model="search" type="search" :placeholder="usingRooms ? 'Search rooms' : 'Search calendars'"
                           class="w-full rounded-md border border-offline-border px-3 py-2 text-sm focus:border-navy focus:outline-none">
                </div>
                <p v-if="loading" class="p-4 text-[13px] text-muted">Loading rooms…</p>
                <p v-else-if="error" class="p-4 text-[13px] text-reserved-text">{{ error }} <button type="button" class="font-semibold underline" @click="load">Try again</button></p>
                <p v-else-if="!items.length" class="p-4 text-[13px] text-muted">Nothing found in this account.</p>
                <ul v-else class="max-h-80 divide-y divide-line-soft overflow-y-auto" role="listbox">
                    <li v-for="item in items" :key="item.id">
                        <button type="button" role="option" :aria-selected="picked?.id === item.id" @click="pick(item)"
                                class="flex w-full items-center gap-3 px-4 py-3 text-left hover:bg-surface-raised" :class="picked?.id === item.id ? 'bg-navy-50' : ''">
                            <span class="flex h-[18px] w-[18px] flex-none items-center justify-center rounded-full border-2" :class="picked?.id === item.id ? 'border-navy' : 'border-offline-border'">
                                <span v-if="picked?.id === item.id" class="h-2 w-2 rounded-full bg-navy"></span>
                            </span>
                            <span class="flex min-w-0 flex-col">
                                <span class="truncate font-semibold">{{ item.name }}</span>
                                <span v-if="item.detail" class="truncate text-xs text-muted">{{ item.detail }}</span>
                            </span>
                        </button>
                    </li>
                </ul>
            </div>

            <label v-if="picked" class="flex flex-col gap-1.5">
                <span class="text-[13px] font-semibold">Name on the tablet</span>
                <input v-model="form.displayName" type="text" required
                       class="max-w-sm rounded-[10px] border border-offline-border bg-surface px-3.5 py-[11px] text-sm focus:border-navy focus:outline-none">
            </label>
            <span v-for="(message, field) in form.errors" :key="field" class="text-xs text-reserved-text">{{ message }}</span>
        </div>

        <div class="flex items-center justify-between">
            <button type="button" class="text-[13px] font-semibold text-muted hover:text-navy" @click="flow.goTo('calendar')">← Back</button>
            <div class="flex items-center gap-2">
                <Button size="lg" :disabled="!picked || form.processing" @click="submit">Create display</Button>
            </div>
        </div>
    </template>
</template>
