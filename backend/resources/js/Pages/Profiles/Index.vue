<script setup>
// Profiles: the list on the left, the selected profile's settings and its displays on the right.
// Editing happens on the profile's own page; a free workspace sees what profiles are for.
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import PortalShell from '../../Components/PortalShell.vue';
import { openUpgrade } from '../../Components/Upgrade/upgrade';
import FlashMessage from '../../Components/FlashMessage.vue';
import Icon from '../../Components/Icon.vue';
import Button from '../../DesignSystem/Button.vue';
import Chip from '../../DesignSystem/Chip.vue';
import EmptyState from '../../DesignSystem/EmptyState.vue';
import Modal from '../../DesignSystem/Modal.vue';
import LinkedDisplays from './Partials/LinkedDisplays.vue';
import { cancelOptions, defaultTexts, timelineOptions } from '../Configure/Partials/fields';

const props = defineProps({
    shell: Object, hasPro: Boolean, profiles: Array, selected: Object, advertisement: Boolean, urls: Object,
});

const onOff = (v) => (v ? 'On' : 'Off');
const shownHidden = (v) => (v ? 'Shown' : 'Hidden');

const groups = computed(() => {
    const v = props.selected?.values;
    if (!v) return [];
    const edit = (anchor) => `${props.selected.editUrl}#${anchor}`;
    const list = [
        {
            title: 'Room status', href: edit('texts'), items: [
                ['Available text', v.text_available || defaultTexts.text_available],
                ['Reserved text', v.text_reserved || defaultTexts.text_reserved],
                ['Meeting titles', shownHidden(v.show_meeting_title)],
                ['Organizer', shownHidden(v.show_organizer)],
            ],
        },
        {
            title: 'Booking and check-in', href: edit('behavior'), items: [
                ['Book from the display', v.booking_enabled ? (v.allow_future_bookings ? 'On, also other days' : 'On, today only') : 'Off'],
                ['Who may end a meeting', cancelOptions.find((o) => o.value === v.cancel_permission)?.label.split(',')[0] ?? '-'],
                ['Check-in', v.check_in_enabled ? `On, opens ${v.check_in_minutes} min before, ${v.check_in_grace_period} min grace` : 'Off'],
                ['Extend a meeting', onOff(v.extend_enabled)],
            ],
        },
        {
            title: 'Display', href: edit('display'), items: [
                ["Today's schedule", timelineOptions.find((o) => o.value === v.timeline_widget_mode)?.label ?? '-'],
                ['View schedule button', onOff(v.view_schedule)],
                ['Status border', { small: 'Small', medium: 'Medium', large: 'Large' }[v.border_thickness] ?? '-'],
                ['All-day events block', v.all_day_events_block ? 'Yes' : 'No'],
            ],
        },
        {
            title: 'Branding', href: edit('branding'), images: true, items: [
                ['Font', v.font_family],
            ],
        },
    ];
    if (props.advertisement) {
        list.push({
            title: 'Advertisement', href: edit('advertisement'), items: [
                ['Advertisement', v.advertisement_enabled ? `On, every ${v.advertisement_interval} min for ${v.advertisement_duration} s` : 'Off'],
            ],
        });
    }
    return list;
});

const summary = (p) => `${p.displays} ${p.displays === 1 ? 'display' : 'displays'}`;
const customised = (p) => (p.customised ? `${p.customised} with own settings` : 'All follow it fully');

const confirmDelete = ref(false);
const deleting = ref(false);
function destroy() {
    deleting.value = true;
    router.delete(props.selected.deleteUrl, { onFinish: () => { deleting.value = false; confirmDelete.value = false; } });
}
</script>

<template>
    <Head title="Profiles" />

    <PortalShell :shell="shell" title="Profiles" pro pro-feature="profiles">
        <template #actions>
            <Button v-if="hasPro" :href="urls.create"><template #icon><Icon name="plus" :size="16" :stroke="2.5" /></template>New profile</Button>
        </template>

        <div class="flex flex-col gap-6 px-6 pt-7 pb-10 md:px-8">
            <p class="max-w-[720px] text-[13px] text-muted">
                Configure many displays at once. A display follows its profile in every section it does not hold its own values for.
            </p>
            <FlashMessage />

            <!-- Free workspace: what profiles are and how to get them, as on the Boards page. -->
            <div v-if="!hasPro" class="sp-card flex max-w-2xl flex-col gap-4 px-6 py-6">
                <div class="flex flex-col gap-1.5">
                    <h2 class="font-display text-xl font-bold tracking-tight">Set up many displays at once</h2>
                    <p class="text-ink-3">A profile is a reusable set of display settings. Link displays to it and they all follow it, so you configure many rooms at once instead of one by one.</p>
                </div>
                <ul class="flex flex-col gap-1.5 text-[13px] text-ink-2">
                    <li class="flex items-center gap-2"><Icon name="check" :size="14" :stroke="2.5" class="text-available" />Check-in, booking, texts, branding and advertisement in one place</li>
                    <li class="flex items-center gap-2"><Icon name="check" :size="14" :stroke="2.5" class="text-available" />Change the profile and every linked display follows</li>
                    <li class="flex items-center gap-2"><Icon name="check" :size="14" :stroke="2.5" class="text-available" />A single display can still keep its own values per section</li>
                </ul>
                <p class="text-xs text-muted">Profiles are part of Pro.</p>
                <div><Button variant="accent" @click="openUpgrade('profiles')">See what Pro adds</Button></div>
            </div>

            <div v-else-if="!profiles.length" class="sp-card max-w-[640px] px-6 py-6">
                <EmptyState title="No profiles yet">
                    Create a profile to manage the settings of many displays at once, then assign it to displays on the Displays page.
                    <template #actions><Button :href="urls.create">New profile</Button></template>
                </EmptyState>
            </div>

            <div v-else class="flex flex-wrap items-start gap-6">
                <section aria-label="Profiles" class="flex min-w-0 flex-[0_1_300px] flex-col gap-2.5">
                    <Link v-for="p in profiles" :key="p.id" :href="p.url" preserve-scroll :aria-current="p.selected ? 'true' : null"
                          class="sp-card flex flex-col gap-2.5 p-4 text-ink transition-colors"
                          :class="p.selected ? 'border-navy shadow-[0_0_0_1px_var(--brand-navy)]' : 'hover:border-navy-400'">
                        <span class="truncate font-semibold">{{ p.name }}</span>
                        <span class="flex justify-between gap-3 border-t border-line-soft pt-2.5 text-xs text-muted">
                            <span><strong class="font-semibold text-ink">{{ p.displays }}</strong> {{ p.displays === 1 ? 'display' : 'displays' }}</span>
                            <span>{{ p.displays ? customised(p) : 'Not used yet' }}</span>
                        </span>
                    </Link>
                    <div class="flex flex-col gap-1.5 rounded-xl border border-dashed border-offline-border px-4 py-3.5 text-xs text-muted">
                        <span class="font-semibold text-ink-2">How it adds up</span>
                        <span>Built-in defaults, then the profile, then a section the display holds on its own. The most specific one wins, per section.</span>
                    </div>
                </section>

                <section v-if="selected" aria-label="Selected profile" class="flex min-w-0 flex-[999_1_560px] flex-col gap-5">
                    <div class="flex flex-wrap items-start gap-4">
                        <div class="flex min-w-0 flex-[1_1_280px] flex-col gap-1">
                            <h2 class="font-display text-[22px] font-bold tracking-tight">{{ selected.name }}</h2>
                            <span class="text-[13px] text-muted">
                                Followed by {{ summary({ displays: selected.displays.length }) }}<template v-if="selected.updated">, last changed {{ selected.updated }}</template><template v-if="selected.createdBy">, created by {{ selected.createdBy }}</template>
                            </span>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <Button v-if="selected.canDelete" variant="secondary" @click="confirmDelete = true">Delete</Button>
                            <Button variant="secondary" :href="urls.displays">Assign displays</Button>
                            <Button v-if="selected.canUpdate" :href="selected.editUrl">Edit profile</Button>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-start gap-5">
                        <div class="flex min-w-0 flex-[999_1_380px] flex-col gap-3.5">
                            <div v-for="g in groups" :key="g.title" class="sp-card flex flex-col gap-3 px-5 py-[18px]">
                                <div class="flex items-center justify-between">
                                    <h3 class="text-sm font-semibold">{{ g.title }}</h3>
                                    <Link v-if="selected.canUpdate" :href="g.href" class="text-xs font-semibold text-navy hover:underline">Edit</Link>
                                </div>
                                <dl class="grid grid-cols-1 gap-x-4 gap-y-2 text-[13px] sm:grid-cols-2">
                                    <div v-for="[label, value] in g.items" :key="label" class="flex min-w-0 flex-col">
                                        <dt class="text-xs text-muted">{{ label }}</dt>
                                        <dd class="truncate font-medium">{{ value }}</dd>
                                    </div>
                                    <template v-if="g.images">
                                        <div class="flex min-w-0 flex-col">
                                            <dt class="text-xs text-muted">Logo</dt>
                                            <dd class="font-medium">
                                                <img v-if="selected.images.logo.url" :src="selected.images.logo.url" alt="Logo" class="mt-1 h-8 max-w-[120px] object-contain">
                                                <span v-else>None</span>
                                            </dd>
                                        </div>
                                        <div class="flex min-w-0 flex-col">
                                            <dt class="text-xs text-muted">Background</dt>
                                            <dd class="font-medium">
                                                <img v-if="selected.images.background.url" :src="selected.images.background.url" alt="Background" class="mt-1 h-10 w-16 rounded-[6px] object-cover">
                                                <span v-else>None</span>
                                            </dd>
                                        </div>
                                    </template>
                                </dl>
                            </div>
                        </div>

                        <div class="min-w-0 flex-[1_1_320px] lg:max-w-[420px]">
                            <LinkedDisplays :displays="selected.displays" :displays-url="urls.displays" />
                        </div>
                    </div>
                </section>
            </div>
        </div>

        <Modal v-if="selected" :open="confirmDelete" title="Delete this profile?" @close="confirmDelete = false">
            <p class="text-ink-2">
                {{ selected.name }} is removed. Displays that follow it keep its settings as their own, so nothing changes on the wall.
            </p>
            <template #footer>
                <Button variant="secondary" @click="confirmDelete = false">Cancel</Button>
                <Button variant="danger" :disabled="deleting" @click="destroy">Delete profile</Button>
            </template>
        </Modal>
    </PortalShell>
</template>
