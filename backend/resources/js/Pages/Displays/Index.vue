<script setup>
import { useRememberedRef } from '../../Composables/useRememberedRef';
import { computed, onBeforeUnmount, onMounted, ref, toRef, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import PortalShell from '../../Components/PortalShell.vue';
import { openUpgrade } from '../../Components/Upgrade/upgrade';
import FlashMessage from '../../Components/FlashMessage.vue';
import Icon from '../../Components/Icon.vue';
import Button from '../../DesignSystem/Button.vue';
import Card from '../../DesignSystem/Card.vue';
import Chip from '../../DesignSystem/Chip.vue';
import EmptyState from '../../DesignSystem/EmptyState.vue';
import StatusDot from '../../DesignSystem/StatusDot.vue';
import { useRoomDay } from '../../Composables/useRoomDay';
import { displayState } from './Partials/displayState';
import BulkBar from './Partials/BulkBar.vue';
import DisplayPanel from './Partials/DisplayPanel.vue';
import DiagnosticsModal from './Partials/DiagnosticsModal.vue';
import RowMenu from './Partials/RowMenu.vue';
import DisplayGlyph from '../../Components/DisplayGlyph.vue';

const props = defineProps({
    shell: Object, displays: Array, selected: String, profiles: Array, hasPro: Boolean, canCreate: Boolean,
    mustUpgrade: Boolean, canBulkAssign: Boolean, showBulkHint: Boolean, connectCode: String, urls: Object,
});

const now = ref(new Date());
const { list } = useRoomDay(toRef(props, 'displays'), now);

// Each display with the status the list and the panel show.
const rows = computed(() => list.value.map((d) => ({ ...d, view: displayState(d) })));

const online = computed(() => props.displays.filter((d) => d.device?.online).length);
const subtitle = computed(() => {
    const n = props.displays.length;
    if (!n) return '';
    return `${n} ${n === 1 ? 'display' : 'displays'}, ${online.value} online`;
});

// Filters: free text over name, room and tablet, plus status and profile.
const query = ref('');
const statusFilter = ref('all');
const profileFilter = ref('any');
const view = useRememberedRef('displays.view', 'table', ['table', 'cards']);
const statusOptions = [
    { value: 'all', label: 'All' },
    { value: 'available', label: 'Free' },
    { value: 'reserved', label: 'In use' },
    { value: 'checkin', label: 'Needs attention' },
    { value: 'offline', label: 'Offline or paused' },
];

const filtered = computed(() => {
    const q = query.value.trim().toLowerCase();
    return rows.value.filter((d) => {
        if (statusFilter.value !== 'all' && d.view.tone !== statusFilter.value) return false;
        if (profileFilter.value === 'none' && d.profile) return false;
        if (!['any', 'none'].includes(profileFilter.value) && d.profile?.name !== profileFilter.value) return false;
        if (!q) return true;
        return [d.name, d.displayName, d.calendar?.detail, d.calendar?.account, ...d.devices.map((t) => t.name)]
            .some((v) => v && v.toLowerCase().includes(q));
    });
});
const filtering = computed(() => query.value.trim() !== '' || statusFilter.value !== 'all' || profileFilter.value !== 'any');
const profileNames = computed(() => [...new Set(props.displays.map((d) => d.profile?.name).filter(Boolean))].sort());

// Bulk selection (Pro, more than one display).
const picked = ref([]);
const allPicked = computed(() => filtered.value.length > 0 && filtered.value.every((d) => picked.value.includes(d.id)));
function toggleAll(e) {
    const ids = filtered.value.map((d) => d.id);
    picked.value = e.target.checked ? [...new Set([...picked.value, ...ids])] : picked.value.filter((id) => !ids.includes(id));
}
watch(() => props.displays, (displays) => { picked.value = picked.value.filter((id) => displays.some((d) => d.id === id)); });

// The side panel, deep-linkable with ?display=<id>.
const openId = ref(props.selected);
const current = computed(() => rows.value.find((d) => d.id === openId.value) ?? null);
function setUrl(id) {
    const url = new URL(window.location.href);
    if (id) url.searchParams.set('display', id); else url.searchParams.delete('display');
    window.history.replaceState(window.history.state, '', url);
}
function open(id) { openId.value = id; setUrl(id); }
function close() { openId.value = null; setUrl(null); }
watch(() => props.selected, (id) => { if (id) openId.value = id; });

// Actions shared by the row menu and the panel.
const busy = ref(false);
const visit = { preserveScroll: true, preserveState: true, onStart: () => { busy.value = true; }, onFinish: () => { busy.value = false; } };
const actions = {
    toggle: (d) => router.patch(d.urls.status, { status: d.status === 'active' ? 'deactivated' : 'active' }, visit),
    makeActive: (d) => router.post(d.urls.makeActive, {}, visit),
    remove: (d) => {
        if (!confirm(`Delete the display ${d.name}? The tablet stops showing this room.`)) return;
        router.delete(d.urls.delete, { ...visit, onSuccess: () => { if (openId.value === d.id) close(); } });
    },
    diagnose: (d) => { diagnosing.value = d; },
};
const diagnosing = ref(null);
// Deferred, so the Escape that closes the modal does not also close the panel behind it.
const closeDiagnostics = () => setTimeout(() => { diagnosing.value = null; });

const onKey = (e) => { if (e.key === 'Escape' && current.value && !diagnosing.value) close(); };

// Live enough: the clock moves every 20 seconds, the displays are fetched again every minute.
let tick;
let poll;
onMounted(() => {
    window.addEventListener('keydown', onKey);
    tick = setInterval(() => { now.value = new Date(); }, 20000);
    poll = setInterval(() => {
        if (busy.value) return;
        router.reload({ only: ['displays'], onSuccess: () => { now.value = new Date(); } });
    }, 60000);
});
onBeforeUnmount(() => { window.removeEventListener('keydown', onKey); clearInterval(tick); clearInterval(poll); });

const fieldClass = 'rounded-lg border border-line bg-surface px-3 py-2 text-[13px] text-ink focus:border-navy focus:outline-none';
</script>

<template>
    <Head title="Displays" />

    <PortalShell :shell="shell" title="Displays">
        <template #actions>
            <span v-if="subtitle" class="text-[13px] text-muted">{{ subtitle }}</span>
            <Button v-if="hasPro" variant="secondary" :href="urls.profiles" external>Manage profiles</Button>
            <template v-if="canCreate">
                <!-- On the free plan with its display in use, the button explains Pro instead of a dead end. -->
                <Button v-if="mustUpgrade" @click="openUpgrade('displays')"><template #icon><Icon name="plus" :size="16" :stroke="2.5" /></template>New display</Button>
                <Button v-else :href="urls.newDisplay" external><template #icon><Icon name="plus" :size="16" :stroke="2.5" /></template>New display</Button>
            </template>
        </template>

        <div class="flex flex-col gap-4 px-6 pt-7 pb-10 md:px-8">
            <FlashMessage />

            <EmptyState v-if="!displays.length" title="No displays yet">
                Connect a calendar, pick a room and pair a tablet. It takes a few minutes.
                <template v-if="canCreate && !mustUpgrade" #actions><Button :href="urls.newDisplay" external>Set up your first display</Button></template>
            </EmptyState>

            <template v-else>
                <div class="flex flex-wrap items-center gap-2.5">
                    <label for="display-filter" class="sr-only">Filter displays</label>
                    <span class="relative flex max-w-[360px] min-w-[220px] flex-[1_1_260px] items-center">
                        <Icon name="search" :size="15" class="pointer-events-none absolute left-3 text-muted" />
                        <input id="display-filter" v-model="query" type="search" placeholder="Filter by name, room or tablet" :class="[fieldClass, 'w-full pl-9']">
                    </span>
                    <label class="inline-flex items-center gap-1.5 text-xs font-medium text-ink-3">
                        Status
                        <select v-model="statusFilter" :class="fieldClass">
                            <option v-for="o in statusOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
                        </select>
                    </label>
                    <label v-if="profileNames.length" class="inline-flex items-center gap-1.5 text-xs font-medium text-ink-3">
                        Profile
                        <select v-model="profileFilter" :class="fieldClass">
                            <option value="any">Any</option>
                            <option value="none">No profile</option>
                            <option v-for="p in profileNames" :key="p" :value="p">{{ p }}</option>
                        </select>
                    </label>
                    <div class="flex-1"></div>
                    <div class="inline-flex overflow-hidden rounded-lg border border-line bg-surface">
                        <button type="button" aria-label="Table view" :aria-pressed="view === 'table'" @click="view = 'table'"
                                class="px-2.5 py-[7px]" :class="view === 'table' ? 'bg-navy text-white' : 'text-ink-3 hover:bg-surface-raised'">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18M3 12h18M3 18h18" /></svg>
                        </button>
                        <button type="button" aria-label="Card view" :aria-pressed="view === 'cards'" @click="view = 'cards'"
                                class="px-2.5 py-[7px]" :class="view === 'cards' ? 'bg-navy text-white' : 'text-ink-3 hover:bg-surface-raised'">
                            <Icon name="boards" :size="15" />
                        </button>
                    </div>
                </div>

                <BulkBar v-if="canBulkAssign" v-model:picked="picked" :displays="displays" :profiles="profiles" :show-hint="showBulkHint" :urls="urls" />

                <Card v-if="view === 'table'" padding="none" class="overflow-x-auto">
                    <div class="min-w-[860px]">
                        <div class="grid items-center gap-3 border-b border-line px-4 py-2.5 text-[11px] font-semibold tracking-[0.04em] text-muted uppercase"
                             :class="canBulkAssign ? 'grid-cols-[28px_2.2fr_1.3fr_1.6fr_1.2fr_1fr_40px]' : 'grid-cols-[2.2fr_1.3fr_1.6fr_1.2fr_1fr_40px]'">
                            <span v-if="canBulkAssign"><input type="checkbox" aria-label="Select all displays" :checked="allPicked" class="h-4 w-4 accent-navy" @change="toggleAll"></span>
                            <span>Display</span><span>Status</span><span>Calendar</span><span>Tablet</span><span>Profile</span><span><span class="sr-only">Actions</span></span>
                        </div>
                        <div v-for="d in filtered" :key="d.id"
                             class="grid cursor-pointer items-center gap-3 border-b border-line-soft px-4 last:border-b-0 hover:bg-surface-raised"
                             @click="open(d.id)"
                             :class="[canBulkAssign ? 'grid-cols-[28px_2.2fr_1.3fr_1.6fr_1.2fr_1fr_40px]' : 'grid-cols-[2.2fr_1.3fr_1.6fr_1.2fr_1fr_40px]', openId === d.id ? 'bg-surface-raised' : '']">
                            <span v-if="canBulkAssign" class="flex h-14 items-center">
                                <input v-model="picked" type="checkbox" :value="d.id" :aria-label="`Select ${d.name}`" class="h-4 w-4 accent-navy" @click.stop>
                            </span>
                            <button type="button" class="flex h-14 min-w-0 cursor-pointer items-center gap-3 text-left text-ink" @click.stop="open(d.id)">
                                <DisplayGlyph :tone="d.view.tone" />
                                <span class="flex min-w-0 flex-col">
                                    <span class="truncate font-semibold">{{ d.name }}</span>
                                    <span v-if="d.displayName && d.displayName !== d.name" class="truncate text-xs text-muted">{{ d.displayName }}</span>
                                </span>
                            </button>
                            <span class="flex min-w-0 flex-col">
                                <span class="inline-flex items-center gap-1.5 text-xs font-semibold" :class="d.view.text"><StatusDot :status="d.view.tone" />{{ d.view.label }}</span>
                                <span v-if="d.view.detail" class="truncate text-xs text-muted">{{ d.view.detail }}</span>
                            </span>
                            <span class="flex min-w-0 flex-col text-[13px]">
                                <template v-if="d.calendar">
                                    <span class="truncate">{{ d.calendar.detail || d.calendar.account }}</span>
                                    <span class="truncate text-xs text-muted">{{ d.calendar.provider }}</span>
                                </template>
                                <span v-else class="text-muted">-</span>
                            </span>
                            <span class="flex min-w-0 flex-col text-[13px]">
                                <template v-if="d.devices.length">
                                    <span class="truncate">{{ d.devices.length === 1 ? d.devices[0].name : `${d.devices.length} tablets` }}</span>
                                    <span class="truncate text-xs text-muted">{{ d.device.online ? 'Online' : 'Offline' }}{{ d.device.lastSeen ? `, seen ${d.device.lastSeen}` : '' }}</span>
                                </template>
                                <span v-else class="text-muted">No tablet</span>
                            </span>
                            <!-- The profile, and on a line of its own (like the other columns) whether it deviates. -->
                            <span class="flex min-w-0 flex-col items-start gap-0.5"
                                  :title="d.profile ? (d.profile.deviates ? `Follows ${d.profile.name}, with its own settings in one or more sections` : `Follows ${d.profile.name}`) : null">
                                <template v-if="d.profile">
                                    <span class="max-w-full truncate rounded-full bg-line-soft px-2.5 py-0.5 text-xs font-medium text-ink-2">{{ d.profile.name }}</span>
                                    <span v-if="d.profile.deviates" class="max-w-full truncate text-xs text-checkin-text">Some own settings</span>
                                </template>
                                <span v-else class="text-[13px] text-muted">-</span>
                            </span>
                            <span class="cursor-default" @click.stop><RowMenu :display="d" :has-pro="hasPro" @open="open(d.id)" @toggle="actions.toggle(d)" @make-active="actions.makeActive(d)" @diagnose="actions.diagnose(d)" @remove="actions.remove(d)" /></span>
                        </div>
                        <p v-if="!filtered.length" class="px-4 py-8 text-center text-[13px] text-muted">No display matches this filter.</p>
                    </div>
                </Card>

                <div v-else class="grid gap-3.5 sm:grid-cols-2 xl:grid-cols-3">
                    <button v-for="d in filtered" :key="d.id" type="button" class="sp-card sp-card-pad-md sp-card-interactive flex cursor-pointer flex-col gap-2.5 text-left text-ink" @click="open(d.id)">
                        <span class="flex items-center gap-2.5">
                            <DisplayGlyph :tone="d.view.tone" />
                            <span class="flex-1 truncate font-semibold">{{ d.name }}</span>
                            <span :class="['sp-badge', `sp-badge-${d.view.tone}`]"><StatusDot :status="d.view.tone" />{{ d.view.label }}</span>
                        </span>
                        <span class="truncate text-[13px] text-ink-3">{{ d.calendar ? (d.calendar.detail || d.calendar.account) : '-' }}</span>
                        <span class="truncate text-xs text-muted">{{ d.view.detail || ' ' }}</span>
                    </button>
                    <p v-if="!filtered.length" class="text-[13px] text-muted">No display matches this filter.</p>
                </div>

                <p v-if="filtering" class="text-xs text-muted">Showing {{ filtered.length }} of {{ displays.length }}</p>
            </template>
        </div>

        <DisplayPanel :display="current" :has-pro="hasPro" :connect-code="connectCode" :urls="urls" :busy="busy"
                      @close="close" @toggle="actions.toggle(current)" @make-active="actions.makeActive(current)"
                      @diagnose="actions.diagnose(current)" @remove="actions.remove(current)" />

        <DiagnosticsModal :display="diagnosing" @close="closeDiagnostics" />
    </PortalShell>
</template>
