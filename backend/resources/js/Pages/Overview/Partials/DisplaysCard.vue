<script setup>
// The overview's Displays card (design 01d): the first displays as on the Displays page, with the
// same inline actions. Pausing and deleting work from here; troubleshooting is on the Displays page.
import { computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import DisplayGlyph from '../../../Components/DisplayGlyph.vue';
import StatusDot from '../../../DesignSystem/StatusDot.vue';
import DisplayActions from '../../Displays/Partials/DisplayActions.vue';
import RowMenu from '../../Displays/Partials/RowMenu.vue';
import Icon from '../../../Components/Icon.vue';
import { openUpgrade } from '../../../Components/Upgrade/upgrade';

const props = defineProps({
    rooms: { type: Array, required: true }, // from useRoomDay, so each carries its state
    hasPro: Boolean,
    allUrl: { type: String, required: true },
    newUrl: { type: String, required: true },
    mustUpgrade: Boolean,
});

const SHOWN = 5;
const tones = { free: 'available', busy: 'reserved', checkin: 'checkin', offline: 'offline' };
const text = { available: 'text-available-text', reserved: 'text-reserved-text', checkin: 'text-checkin-text', offline: 'text-ink-3' };
const rows = computed(() => props.rooms.slice(0, SHOWN).map((r) => {
    const tone = tones[r.state.key] ?? 'offline';
    return { ...r, tone, text: text[tone] };
}));
const more = computed(() => props.rooms.length - SHOWN);

const visit = { preserveScroll: true };
const actions = {
    toggle: (d) => router.patch(d.urls.status, { status: d.status === 'active' ? 'deactivated' : 'active' }, visit),
    makeActive: (d) => router.post(d.urls.makeActive, {}, visit),
    remove: (d) => {
        if (!confirm(`Delete the display ${d.name}? The tablet stops showing this room.`)) return;
        router.delete(d.urls.delete, visit);
    },
};
// No calendar column here: the card sits beside the quick actions; Details and the Displays page show it.
const columns = 'grid-cols-[2fr_1.2fr_1.3fr_1fr_256px]';
</script>

<template>
    <section aria-label="Displays" class="sp-card gap-0 overflow-hidden">
        <div class="flex items-center gap-3 px-5 pt-4 pb-3">
            <h2 class="font-display text-lg font-bold tracking-tight text-navy">Displays</h2>
            <span class="flex-1"></span>
            <Link :href="allUrl" class="sp-btn sp-btn-secondary sp-btn-sm">Show all<Icon name="arrowRight" :size="14" /></Link>
            <button v-if="mustUpgrade" type="button" class="sp-btn sp-btn-secondary sp-btn-sm" @click="openUpgrade('displays')"><Icon name="plus" :size="14" :stroke="2.5" />New display</button>
            <Link v-else :href="newUrl" class="sp-btn sp-btn-secondary sp-btn-sm"><Icon name="plus" :size="14" :stroke="2.5" />New display</Link>
        </div>
        <div class="overflow-x-auto">
            <div class="min-w-[760px]">
                <div class="grid items-center gap-3 border-y border-line-soft px-5 py-2.5 text-[11px] font-semibold tracking-[0.04em] text-muted uppercase" :class="columns">
                    <span>Display</span><span>Status</span><span>Tablet</span><span>Profile</span><span><span class="sr-only">Actions</span></span>
                </div>
                <div v-for="d in rows" :key="d.id" class="grid items-center gap-3 border-b border-line-soft px-5 last:border-b-0 hover:bg-surface-raised" :class="columns">
                    <Link :href="d.urls.details" class="flex h-14 min-w-0 items-center gap-3 text-ink">
                        <DisplayGlyph :tone="d.tone" />
                        <span class="flex min-w-0 flex-col">
                            <span class="truncate font-semibold">{{ d.name }}</span>
                            <span v-if="d.location" class="truncate text-xs text-muted">{{ d.location }}</span>
                        </span>
                    </Link>
                    <span class="inline-flex min-w-0 items-center gap-1.5 text-xs font-semibold" :class="d.text"><StatusDot :status="d.tone" /><span class="truncate">{{ d.state.label }}</span></span>
                    <span class="flex min-w-0 flex-col text-[13px]">
                        <template v-if="d.device">
                            <span class="truncate">{{ d.device.name }}</span>
                            <span class="truncate text-xs text-muted">{{ d.device.online ? 'Online' : `Offline, seen ${d.device.lastSeen}` }}</span>
                        </template>
                        <span v-else class="text-muted">No tablet</span>
                    </span>
                    <span class="min-w-0">
                        <span v-if="d.profile" class="sp-chip sp-chip-neutral max-w-full truncate">{{ d.profile }}</span>
                        <span v-else class="text-[13px] text-muted">-</span>
                    </span>
                    <DisplayActions :configure-url="d.urls.configure" :has-pro="hasPro" :details-href="d.urls.details">
                        <RowMenu :display="d" :has-pro="hasPro" no-diagnose @toggle="actions.toggle(d)" @make-active="actions.makeActive(d)" @remove="actions.remove(d)" />
                    </DisplayActions>
                </div>
            </div>
        </div>
        <Link v-if="more > 0" :href="allUrl" class="border-t border-line-soft px-5 py-2.5 text-xs text-muted hover:text-navy">{{ more }} more {{ more === 1 ? 'display' : 'displays' }}</Link>
    </section>
</template>
