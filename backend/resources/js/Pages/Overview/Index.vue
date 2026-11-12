<script setup>
import { computed, onBeforeUnmount, onMounted, ref, toRef } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import PortalShell from '../../Components/PortalShell.vue';
import FlashMessage from '../../Components/FlashMessage.vue';
import Icon from '../../Components/Icon.vue';
import Button from '../../DesignSystem/Button.vue';
import { useRoomDay } from '../../Composables/useRoomDay';
import RoomsNow from './Partials/RoomsNow.vue';
import KeyFigures from './Partials/KeyFigures.vue';
import Occupancy from './Partials/Occupancy.vue';
import FinishSetup from './Partials/FinishSetup.vue';
import ProfileCard from './Partials/ProfileCard.vue';
import CancellationCard from './Partials/CancellationCard.vue';

const props = defineProps({
    shell: Object, notices: Array, rooms: Array, accounts: Array, checklist: Object, hasPro: Boolean,
    connectCode: String, profileCard: Object, cancellation: Object, selfHosted: Object, urls: Object,
});

const now = ref(new Date());
const { list, hours, figures, occupancy, nowLeft, nowLabel } = useRoomDay(toRef(props, 'rooms'), now);

const occupancyLabels = computed(() => hours.value);
const firstName = computed(() => (props.shell.user || '').split(/[\s.@]+/)[0]);
const greeting = computed(() => {
    const h = now.value.getHours();
    const part = h < 12 ? 'Good morning' : h < 18 ? 'Good afternoon' : 'Good evening';
    return firstName.value ? `${part}, ${firstName.value.charAt(0).toUpperCase()}${firstName.value.slice(1)}!` : `${part}!`;
});
// One sentence on how things stand.
const summary = computed(() => {
    const f = figures.value;
    if (!props.rooms.length) return 'Welcome to Spacepad. Set up your first display and its room shows up here, live.';
    if (props.notices.length) return `${props.notices.length === 1 ? 'One thing needs' : `${props.notices.length} things need`} your attention. The rest runs as it should.`;
    if (!f.live) return 'Welcome back. Your rooms show up here as soon as their tablets are online.';
    return `Welcome back. ${f.free} of ${f.live} ${f.live === 1 ? 'room is' : 'rooms are'} free right now, with ${f.bookings} ${f.bookings === 1 ? 'booking' : 'bookings'} today.`;
});
const code = computed(() => props.connectCode?.match(/.{1,3}/g)?.join(' '));

const tone = {
    danger: 'border-reserved-border bg-reserved-bg text-reserved-text',
    warning: 'border-checkin bg-checkin-bg text-checkin-text',
};

// Live enough: the clock moves every 20 seconds, the rooms are fetched again every minute.
let tick;
let poll;
onMounted(() => {
    tick = setInterval(() => { now.value = new Date(); }, 20000);
    poll = setInterval(() => router.reload({ only: ['rooms', 'notices'], onSuccess: () => { now.value = new Date(); } }), 60000);
});
onBeforeUnmount(() => { clearInterval(tick); clearInterval(poll); });
</script>

<template>
    <Head title="Overview" />

    <PortalShell :shell="shell" title="Overview">
        <template #actions>
            <Button :href="urls.newDisplay"><template #icon><Icon name="plus" :size="16" :stroke="2.5" /></template>New display</Button>
        </template>

        <div class="flex flex-col gap-6 px-6 pt-7 pb-10 md:px-8">
            <div class="flex flex-col gap-1">
                <h2 class="font-display text-[26px] leading-tight font-bold tracking-tight">{{ greeting }}</h2>
                <p class="text-muted">{{ summary }}</p>
            </div>

            <FlashMessage />

            <section v-if="notices.length" aria-label="Needs attention" class="flex flex-col gap-2.5">
                <div v-for="(n, i) in notices" :key="i" class="flex flex-wrap items-center gap-4 rounded-xl border px-4 py-3 text-[13px]" :class="tone[n.tone]">
                    <span class="flex-none rounded-lg border-[1.5px] border-current bg-surface px-2 py-0.5 text-[11px] font-semibold whitespace-nowrap">{{ n.tag }}</span>
                    <span class="flex min-w-0 flex-1 flex-col"><span class="font-semibold">{{ n.title }}</span><span class="text-xs opacity-85">{{ n.body }}</span></span>
                    <a :href="n.href" class="rounded-lg bg-navy px-3 py-1.5 text-xs font-semibold whitespace-nowrap text-white hover:bg-navy-700">{{ n.cta }}</a>
                </div>
            </section>

            <div class="flex flex-wrap items-start gap-6">
                <div class="flex min-w-0 flex-[999_1_560px] flex-col gap-4">
                    <KeyFigures v-if="rooms.length" :figures="figures" :accounts="accounts" />
                    <RoomsNow :rooms="list" :hours="hours" :now-left="nowLeft" :now-label="nowLabel" :urls="urls" />
                    <Occupancy v-if="occupancy.length" :hours="occupancy" :labels="occupancyLabels" />
                </div>

                <aside aria-label="Setup" class="flex max-w-full min-w-0 flex-[1_1_320px] flex-col gap-4 lg:max-w-[380px]">
                    <CancellationCard v-if="cancellation" :card="cancellation" />
                    <ProfileCard v-if="profileCard" :card="profileCard" />
                    <FinishSetup :checklist="checklist" :has-pro="hasPro" :urls="urls" />

                    <div v-if="code" class="sp-card flex flex-col gap-2 px-5 py-[18px]">
                        <h2 class="font-display text-base font-bold tracking-tight">Pair a tablet</h2>
                        <p class="text-[13px] text-muted">Open the Spacepad app on the tablet and enter this code.</p>
                        <span class="self-start rounded-lg border border-dashed border-offline-border px-3 py-1.5 font-mono text-lg font-semibold tracking-[0.12em]">{{ code }}</span>
                    </div>


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
