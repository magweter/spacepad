<script setup>
import { computed, onBeforeUnmount, onMounted, ref, toRef } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { openUpgrade } from '../../Components/Upgrade/upgrade';
import PortalShell from '../../Components/PortalShell.vue';
import Button from '../../DesignSystem/Button.vue';
import Chip from '../../DesignSystem/Chip.vue';
import EmptyState from '../../DesignSystem/EmptyState.vue';
import { useRoomDay } from '../../Composables/useRoomDay';
import QuickActions from './Partials/QuickActions.vue';
import DisplaysCard from './Partials/DisplaysCard.vue';
import ListCard from './Partials/ListCard.vue';
import UpgradeBanner from './Partials/UpgradeBanner.vue';
import FinishSetup from './Partials/FinishSetup.vue';
import ProfileCard from './Partials/ProfileCard.vue';
import CancellationCard from './Partials/CancellationCard.vue';
import AlertRow from '../../Components/AlertRow.vue';
import { dismissAlert, doneAlerts } from '../../Components/alertDismiss';
import AttentionList from '../../Components/Attention/AttentionList.vue';
import AttentionRow from '../../Components/Attention/AttentionRow.vue';

const props = defineProps({
    shell: Object, notices: Array, rooms: Array, checklist: Object, hasPro: Boolean,
    connectCode: String, profileCard: Object, cancellation: Object, selfHosted: Object, urls: Object,
    boards: Object, profiles: Object,
});

const page = usePage();
const now = ref(new Date());
// Today's figures come from the page, reloaded every minute.
const { figures, list: roomList } = useRoomDay(toRef(props, 'rooms'), now);

// Rooms a meeting can be booked in from the portal; the booking itself happens on Live view.
const canBook = computed(() => props.rooms.some((r) => r.actions?.book));

const firstName = computed(() => (props.shell.user || '').split(/[\s.@]+/)[0]);
const greeting = computed(() => {
    const h = now.value.getHours();
    const part = h < 12 ? 'Good morning' : h < 18 ? 'Good afternoon' : 'Good evening';
    return firstName.value ? `${part}, ${firstName.value.charAt(0).toUpperCase()}${firstName.value.slice(1)}!` : `${part}!`;
});
// Alerts set by an admin join the notices here; an info, new or resolved alert is not counted.
const alerts = computed(() => (Array.isArray(page.props.alerts) ? page.props.alerts : []));
// Attention items can be dismissed. An alert from Spacepad is dismissed on the server, the same as
// "Got it" on its popup, so it is gone in every browser. A notice is remembered in this browser by
// its content, so a notice that changes, or a new one, shows again.
const DISMISSED = 'overview.attention.dismissed';
const readDismissed = () => { try { return JSON.parse(localStorage.getItem(DISMISSED) || '[]'); } catch { return []; } };
const dismissed = ref(readDismissed());
const noticeKey = (n) => `notice:${n.tag}:${n.title}:${n.body}`;
function dismiss(key) {
    dismissed.value = [...dismissed.value, key].slice(-100);
    try { localStorage.setItem(DISMISSED, JSON.stringify(dismissed.value)); } catch { /* private mode: only for this visit */ }
}
const hideAlert = (a) => dismissAlert(a);
const shownAlerts = computed(() => alerts.value.filter((a) => !doneAlerts.value.includes(a.id)));
const shownNotices = computed(() => props.notices.filter((n) => !dismissed.value.includes(noticeKey(n))));
// Everything in the attention list: the notices and the alerts from Spacepad that are not dismissed.
const attentionCount = computed(() => shownNotices.value.length + shownAlerts.value.length);
// One cheerful sentence on how things stand, with the number of attention items when there are any.
const plural = (n, one, many) => `${n} ${n === 1 ? one : many}`;
const summary = computed(() => {
    const f = figures.value;
    if (!props.rooms.length) return 'Welcome aboard! Set up your first display and watch its room come to life right here.';
    if (attentionCount.value) {
        const items = attentionCount.value === 1 ? 'One thing could' : `${attentionCount.value} things could`;
        return `${items} use a quick look below. Everything else is humming along.`;
    }
    if (!f.live) return 'All quiet for now. Your rooms light up here as soon as their tablets come online.';
    const bookings = f.bookings ? `${plural(f.bookings, 'booking is', 'bookings are')} lined up today` : 'the day is still wide open';
    return `Looking good! ${f.free} of ${plural(f.live, 'room is', 'rooms are')} free right now, and ${bookings}.`;
});
const code = computed(() => props.connectCode?.match(/.{1,3}/g)?.join(' '));

// The notices' tones as tag colours: blocking is red, the rest amber.
const noticeTone = { danger: 'reserved', warning: 'amber' };

// Live enough: the clock moves every 20 seconds, the rooms are fetched again every minute.
let tick;
let poll;
onMounted(() => {
    tick = setInterval(() => { now.value = new Date(); }, 20000);
    poll = setInterval(() => router.reload({ only: ['rooms', 'notices', 'alerts'], onSuccess: () => { now.value = new Date(); } }), 60000);
});
onBeforeUnmount(() => { clearInterval(tick); clearInterval(poll); });
</script>

<template>
    <Head title="Overview" />

    <PortalShell :shell="shell" :title="greeting" :subtitle="summary">
        <div class="flex flex-col gap-6 px-6 pt-6 pb-10 md:px-10">
            <UpgradeBanner v-if="!hasPro" :self-hosted="!!selfHosted" :pricing-url="urls.pricing" />

            <AttentionList v-if="shownNotices.length || shownAlerts.length">
                <AlertRow v-for="a in shownAlerts" :key="a.id" :tone="a.tone" :title="a.title" :body="a.body" dismissible @dismiss="hideAlert(a)" />
                <AttentionRow v-for="n in shownNotices" :key="noticeKey(n)" :tone="noticeTone[n.tone] ?? 'amber'" :tag="n.tag" :title="n.title" :body="n.body"
                              :cta="n.cta" :href="n.upgrade ? null : n.href" external dismissible
                              @action="openUpgrade(n.upgrade)" @dismiss="dismiss(noticeKey(n))" />
            </AttentionList>

            <div class="grid items-start gap-6 lg:grid-cols-4">
                <div class="flex min-w-0 flex-col gap-6 lg:col-span-3">
                    <DisplaysCard v-if="rooms.length" :rooms="roomList" :has-pro="hasPro" :all-url="urls.displays" :new-url="urls.newDisplay" :must-upgrade="!!page.props.upgrade?.mustUpgradeForDisplay" />
                    <EmptyState v-else title="No displays yet" class="sp-card">Set up a display and its room shows up here and on Live view.
                        <template #actions><Button :href="urls.newDisplay">Set up a display</Button></template>
                    </EmptyState>
                    <template v-if="boards || profiles">
                        <ListCard v-if="boards" title="Boards" :all-url="urls.boards" :new-url="urls.boardsCreate" new-label="New board"
                                  :total="boards.total" :shown="boards.items.length" empty="No boards yet. A board shows many rooms at once, on a TV or in the browser.">
                            <div v-for="b in boards.items" :key="b.id" class="flex items-center gap-3.5 px-5 py-3">
                                <span class="flex min-w-0 flex-1 flex-col gap-0.5">
                                    <span class="truncate text-sm font-semibold text-navy">{{ b.name }}</span>
                                    <span class="text-xs text-muted">{{ b.meta }}</span>
                                </span>
                                <Chip :tone="b.public ? 'available' : 'neutral'">{{ b.public ? 'Public link' : 'Private' }}</Chip>
                                <a :href="b.showUrl" target="_blank" rel="noopener" class="sp-btn sp-btn-secondary sp-btn-sm">Open</a>
                                <a :href="b.editUrl" class="sp-btn sp-btn-secondary sp-btn-sm">Edit</a>
                            </div>
                        </ListCard>
                        <ListCard v-if="profiles" title="Profiles" :all-url="urls.profiles" :new-url="urls.profilesCreate" new-label="New profile"
                                  :total="profiles.total" :shown="profiles.items.length" empty="No profiles yet. A profile sets up many displays at once.">
                            <div v-for="p in profiles.items" :key="p.id" class="flex items-center gap-3.5 px-5 py-3">
                                <span class="min-w-0 flex-1 truncate text-sm font-semibold text-navy">{{ p.name }}</span>
                                <span class="text-xs whitespace-nowrap text-muted">{{ p.displays ? `${p.displays} ${p.displays === 1 ? 'display' : 'displays'}` : 'Not used yet' }}</span>
                                <Link :href="p.editUrl" class="sp-btn sp-btn-secondary sp-btn-sm">Edit</Link>
                            </div>
                        </ListCard>
                    </template>
                </div>

                <aside aria-label="Setup" class="flex min-w-0 flex-col gap-4">
                    <CancellationCard v-if="cancellation" :card="cancellation" />
                    <ProfileCard v-if="profileCard" :card="profileCard" />
                    <div v-if="code" class="sp-card flex flex-col gap-2 px-5 py-[18px]">
                        <h2 class="font-display text-base font-bold tracking-tight">Pair a tablet</h2>
                        <p class="text-[13px] text-muted">Open the Spacepad app on the tablet and enter this code.</p>
                        <span class="self-start rounded-lg border border-dashed border-offline-border px-3 py-1.5 font-mono text-lg font-semibold tracking-[0.12em]">{{ code }}</span>
                    </div>
                    <QuickActions v-if="rooms.length" :has-pro="hasPro" :can-book="canBook" :urls="urls" />
                    <FinishSetup :checklist="checklist" :has-pro="hasPro" :urls="urls" />
                    <div v-if="selfHosted" class="sp-card flex flex-col gap-1 px-5 py-[18px] text-[13px]">
                        <h2 class="font-display text-base font-bold tracking-tight">This instance</h2>
                        <span class="text-muted">Version {{ selfHosted.version }}</span>
                        <span v-if="selfHosted.appUrl" class="truncate text-muted">{{ selfHosted.appUrl }}</span>
                    </div>
                </aside>
            </div>
        </div>
    </PortalShell>
</template>
