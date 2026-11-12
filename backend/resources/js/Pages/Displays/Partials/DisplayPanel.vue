<script setup>
// The device view: one display's status, tablets, calendar, today and profile, sliding in from
// the right. It works on the row data the list already has, so opening it costs no request.
import { computed, ref } from 'vue';
import Button from '../../../DesignSystem/Button.vue';
import Notice from '../../../DesignSystem/Notice.vue';
import StatusDot from '../../../DesignSystem/StatusDot.vue';
import Icon from '../../../Components/Icon.vue';

const props = defineProps({ display: Object, hasPro: Boolean, connectCode: String, urls: Object, busy: Boolean });
const emit = defineEmits(['close', 'toggle', 'makeActive', 'diagnose', 'remove']);

const hm = (date) => date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', hour12: false });
const code = computed(() => props.connectCode?.match(/.{1,3}/g)?.join(' '));
const copied = ref(false);
async function copy() {
    try {
        await navigator.clipboard.writeText(props.connectCode);
        copied.value = true;
        setTimeout(() => { copied.value = false; }, 2000);
    } catch {
        copied.value = false;
    }
}

const bookings = computed(() => {
    const now = new Date();
    return (props.display?.bookings ?? []).map((b) => {
        const live = b.start <= now && b.end > now;
        const past = b.end <= now;
        let note = '';
        if (live) note = b.checkInUntil && now < b.checkInUntil ? 'Waiting for check-in' : 'Now';
        else if (!past && b.checkInUntil) note = 'Check-in required';
        else if (b.tablet) note = 'Booked on tablet';
        return { ...b, live, past, note, time: `${hm(b.start)} to ${hm(b.end)}` };
    });
});

const badge = { available: 'sp-badge-available', reserved: 'sp-badge-reserved', checkin: 'sp-badge-checkin', offline: 'sp-badge-offline' };
const heading = 'text-xs font-semibold tracking-[0.04em] text-muted uppercase';
</script>

<template>
    <Transition enter-from-class="translate-x-full" leave-to-class="translate-x-full" enter-active-class="transition-transform duration-200 ease-out" leave-active-class="transition-transform duration-150 ease-in">
        <aside v-if="display" aria-label="Display details"
               class="fixed inset-y-0 right-0 z-40 flex w-[440px] max-w-full flex-col border-l border-line bg-surface text-ink shadow-[-16px_0_48px_rgba(15,23,42,0.12)]">
            <div class="flex items-start gap-3 border-b border-line px-6 pt-5 pb-4">
                <div class="flex min-w-0 flex-1 flex-col gap-1">
                    <span v-if="display.calendar?.detail" class="truncate text-xs text-muted">{{ display.calendar.detail }}</span>
                    <h2 class="font-display text-xl font-bold tracking-tight">{{ display.name }}</h2>
                    <span v-if="display.displayName && display.displayName !== display.name" class="text-xs text-muted">Shown on the tablet as {{ display.displayName }}</span>
                    <span :class="['sp-badge', badge[display.view.tone], 'self-start']">
                        <StatusDot :status="display.view.tone" />{{ display.view.label }}{{ display.view.detail ? `, ${display.view.detail}` : '' }}
                    </span>
                </div>
                <button type="button" aria-label="Close details" class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-line text-ink-3 hover:text-ink" @click="emit('close')">
                    <Icon name="close" :size="16" />
                </button>
            </div>

            <!-- One main action, the others as a quiet group, delete apart on the right. -->
            <div class="flex items-center gap-2 border-b border-line px-6 py-3.5">
                <a v-if="hasPro" :href="display.urls.configure"
                   class="inline-flex h-9 flex-1 items-center justify-center gap-2 rounded-lg whitespace-nowrap bg-navy px-3 text-[13px] font-semibold text-white hover:bg-navy-700">
                    <Icon name="settings" :size="15" />Configure
                </a>
                <a v-else :href="urls.upgrade" title="Configuring a display is part of Pro"
                   class="inline-flex h-9 flex-1 items-center justify-center gap-2 rounded-lg whitespace-nowrap border border-line bg-surface px-3 text-[13px] font-semibold text-ink-2 hover:border-navy hover:text-navy">
                    <Icon name="settings" :size="15" />Configure
                    <span class="rounded-md border border-info-border bg-info-bg px-1.5 py-px text-[11px] font-semibold text-info">Pro</span>
                </a>

                <div class="inline-flex h-9 overflow-hidden rounded-lg border border-line bg-surface">
                    <button v-if="display.status === 'requires_pro'" type="button" :disabled="busy" title="Use this display instead of the current active one"
                            class="inline-flex items-center gap-1.5 px-3 text-[13px] whitespace-nowrap font-semibold text-ink-2 hover:bg-surface-raised hover:text-ink disabled:opacity-50" @click="emit('makeActive')">
                        <Icon name="play" :size="14" />Make active
                    </button>
                    <button v-else type="button" :disabled="busy" :title="display.status === 'active' ? 'Stop showing this display on its tablet' : 'Show this display on its tablet again'"
                            class="inline-flex items-center gap-1.5 px-3 text-[13px] whitespace-nowrap font-semibold text-ink-2 hover:bg-surface-raised hover:text-ink disabled:opacity-50" @click="emit('toggle')">
                        <Icon :name="display.status === 'active' ? 'pause' : 'play'" :size="14" />{{ display.status === 'active' ? 'Pause' : 'Resume' }}
                    </button>
                    <button v-if="hasPro" type="button" title="Troubleshoot the calendar sync"
                            class="inline-flex items-center gap-1.5 border-l border-line px-3 text-[13px] whitespace-nowrap font-semibold text-ink-2 hover:bg-surface-raised hover:text-ink" @click="emit('diagnose')">
                        <Icon name="search" :size="14" />Troubleshoot
                    </button>
                </div>

                <button type="button" :disabled="busy" aria-label="Delete display" title="Delete display"
                        class="inline-flex h-9 w-9 flex-none items-center justify-center rounded-lg border border-line bg-surface text-muted hover:border-reserved-border hover:bg-reserved-bg hover:text-reserved-text disabled:opacity-50" @click="emit('remove')">
                    <Icon name="trash" :size="15" />
                </button>
            </div>

            <div class="flex flex-col gap-[22px] overflow-y-auto px-6 py-5">
                <Notice v-if="display.status === 'error'" tone="warning" title="Calendar sync failed">
                    Try reconnecting the calendar account, then resume the display.
                </Notice>
                <Notice v-else-if="display.status === 'requires_pro'" tone="warning" title="Waiting for Pro" action="Upgrade" :href="urls.upgrade">
                    The free plan shows one display. Make this one the active display, or upgrade to bring them all back.
                </Notice>
                <Notice v-else-if="display.status === 'deactivated'" tone="neutral" title="Paused">
                    The tablet shows no room information until you resume the display.
                </Notice>

                <section class="flex flex-col gap-2.5">
                    <div class="flex items-center justify-between">
                        <h3 :class="heading">{{ display.devices.length > 1 ? 'Tablets' : 'Tablet' }}</h3>
                        <a :href="urls.pairing" class="text-xs font-semibold text-navy hover:underline">Pair a tablet</a>
                    </div>
                    <ul v-if="display.devices.length" class="flex flex-col gap-2 text-[13px]">
                        <li v-for="(t, i) in display.devices" :key="i" class="flex items-center gap-3 rounded-[10px] border border-line px-3 py-2.5">
                            <Icon name="tablet" :size="18" class="flex-none text-ink-3" />
                            <span class="flex min-w-0 flex-1 flex-col">
                                <span class="truncate font-semibold">{{ t.name || 'Tablet' }}</span>
                                <span class="text-xs text-muted">{{ t.lastSeen ? `Last seen ${t.lastSeen}` : 'Not seen yet' }}</span>
                            </span>
                            <span :class="['sp-badge', t.online ? 'sp-badge-available' : 'sp-badge-offline']"><StatusDot :status="t.online ? 'available' : 'offline'" />{{ t.online ? 'Online' : 'Offline' }}</span>
                        </li>
                    </ul>
                    <p v-else class="text-[13px] text-muted">No tablet paired yet. Open the Spacepad app on a tablet and enter the connect code below.</p>
                </section>

                <section class="flex flex-col gap-2.5">
                    <h3 :class="heading">Calendar</h3>
                    <div v-if="display.calendar" class="flex items-center gap-3 rounded-[10px] border border-line p-3">
                        <span class="inline-flex h-9 w-9 flex-none items-center justify-center rounded-lg bg-info-bg text-info"><Icon name="calendars" :size="18" /></span>
                        <span class="flex min-w-0 flex-1 flex-col text-[13px]">
                            <span class="truncate font-semibold">{{ display.calendar.detail || display.calendar.account }}</span>
                            <span class="truncate text-xs text-muted">{{ [display.calendar.provider, display.calendar.account, display.calendar.isRoom ? 'room' : null].filter(Boolean).join(', ') }}</span>
                            <span v-if="display.lastSync" class="text-xs text-muted">Synced {{ display.lastSync }}</span>
                        </span>
                        <a :href="urls.calendars" class="text-xs font-semibold text-navy hover:underline">Calendars</a>
                    </div>
                    <p v-else class="text-[13px] text-muted">No calendar linked.</p>
                </section>

                <section class="flex flex-col gap-2.5">
                    <div class="flex items-center justify-between">
                        <h3 :class="heading">Today</h3>
                        <span v-if="display.hasData" class="text-xs text-muted">{{ bookings.length }} {{ bookings.length === 1 ? 'booking' : 'bookings' }}</span>
                    </div>
                    <p v-if="!display.hasData" class="text-[13px] text-muted">No data yet. The bookings show up once the tablet has fetched them.</p>
                    <p v-else-if="!bookings.length" class="text-[13px] text-muted">No bookings today.</p>
                    <ul v-else class="flex flex-col gap-2 text-[13px]">
                        <li v-for="(b, i) in bookings" :key="i" class="flex items-center gap-3 rounded-lg px-2.5 py-2"
                            :class="b.live ? (b.note === 'Waiting for check-in' ? 'bg-checkin-bg' : 'bg-reserved-bg') : 'bg-surface-raised'">
                            <span class="w-[104px] flex-none font-semibold tabular-nums" :class="b.live ? (b.note === 'Waiting for check-in' ? 'text-checkin-text' : 'text-reserved-text') : b.past ? 'text-muted' : 'text-ink-2'">{{ b.time }}</span>
                            <span class="min-w-0 flex-1 truncate" :class="b.past ? 'text-muted' : ''">{{ b.title }}</span>
                            <span v-if="b.note" class="text-xs" :class="b.live ? 'font-semibold ' + (b.note === 'Waiting for check-in' ? 'text-checkin-text' : 'text-reserved-text') : 'text-muted'">{{ b.note }}</span>
                        </li>
                    </ul>
                </section>

                <section class="flex flex-col gap-2.5">
                    <h3 :class="heading">Profile</h3>
                    <div class="flex items-center gap-3 rounded-[10px] border border-line p-3 text-[13px]">
                        <span class="flex min-w-0 flex-1 flex-col">
                            <template v-if="display.profile">
                                <span class="truncate font-semibold">{{ display.profile.name }}</span>
                                <span class="text-xs text-muted">{{ display.profile.deviates ? 'Follows the profile, with its own settings in one or more sections' : 'Follows the profile in every section' }}</span>
                            </template>
                            <template v-else>
                                <span class="font-semibold">No profile</span>
                                <span class="text-xs text-muted">{{ display.hasOwnSettings ? 'Uses its own settings' : 'Uses the default settings' }}</span>
                            </template>
                        </span>
                        <a v-if="hasPro" :href="display.urls.configure" class="text-xs font-semibold text-navy hover:underline">Edit</a>
                    </div>
                </section>

                <section v-if="code" class="flex flex-col gap-2">
                    <h3 :class="heading">Connect code</h3>
                    <div class="flex flex-wrap items-center gap-2.5">
                        <code class="rounded-lg bg-line-soft px-3.5 py-2 font-mono text-lg tracking-[0.18em] text-navy">{{ code }}</code>
                        <Button size="sm" variant="secondary" @click="copy"><template #icon><Icon :name="copied ? 'check' : 'copy'" :size="14" /></template>{{ copied ? 'Copied' : 'Copy' }}</Button>
                    </div>
                    <span class="text-xs text-muted">Enter it in the Spacepad app on the tablet, then pick this display.</span>
                </section>
            </div>
        </aside>
    </Transition>
</template>
