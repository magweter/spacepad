<script setup>
// Profiles: an overview of every profile as a card, like Boards. A card opens the profile's own
// page, where it is edited, deleted and its displays are listed. A free workspace sees what
// profiles are for.
import { Head, Link } from '@inertiajs/vue3';
import PortalShell from '../../Components/PortalShell.vue';
import { openUpgrade } from '../../Components/Upgrade/upgrade';
import Icon from '../../Components/Icon.vue';
import Button from '../../DesignSystem/Button.vue';
import Chip from '../../DesignSystem/Chip.vue';
import EmptyState from '../../DesignSystem/EmptyState.vue';

defineProps({ shell: Object, hasPro: Boolean, profiles: Array, urls: Object });

const used = (p) => (p.displays
    ? `${p.displays} ${p.displays === 1 ? 'display' : 'displays'}${p.customised ? `, ${p.customised} with own settings` : ''}`
    : 'Not used yet');
const changed = (p) => [p.updated && `Changed ${p.updated}`, p.createdBy && `by ${p.createdBy}`].filter(Boolean).join(' ');
</script>

<template>
    <Head title="Profiles" />

    <PortalShell :shell="shell" title="Profiles">
        <div class="flex flex-col gap-6 px-6 pt-7 pb-10 md:px-8">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <p class="max-w-[720px] text-[13px] text-muted">
                    Configure many displays at once. A display follows its profile in every section it does not hold its own values for.
                </p>
                <Button v-if="hasPro" :href="urls.create"><template #icon><Icon name="plus" :size="16" :stroke="2.5" /></template>New profile</Button>
            </div>

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

            <!-- The same empty card as on the Boards page. -->
            <div v-else-if="!profiles.length" class="sp-card">
                <EmptyState title="No profiles yet">
                    Create a profile to manage the settings of many displays at once, then assign it to displays on the Displays page.
                    <template #actions>
                        <Button :href="urls.create"><template #icon><Icon name="plus" :size="16" :stroke="2.5" /></template>Create a profile</Button>
                    </template>
                </EmptyState>
            </div>

            <section v-else aria-label="Your profiles" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                <component :is="p.canUpdate ? Link : 'article'" v-for="p in profiles" :key="p.id" :href="p.canUpdate ? p.editUrl : undefined"
                           class="sp-card flex flex-col gap-3 p-4 text-ink" :class="p.canUpdate ? 'sp-card-interactive' : ''">
                    <div class="flex flex-col gap-0.5">
                        <span class="truncate font-semibold">{{ p.name }}</span>
                        <span class="text-xs text-muted">{{ used(p) }}</span>
                    </div>
                    <div class="flex flex-wrap gap-1.5">
                        <Chip :tone="p.booking ? 'info' : 'neutral'">Booking {{ p.booking ? 'on' : 'off' }}</Chip>
                        <Chip :tone="p.checkIn ? 'info' : 'neutral'">Check-in {{ p.checkIn ? 'on' : 'off' }}</Chip>
                        <Chip v-if="p.font" tone="neutral">{{ p.font }}</Chip>
                    </div>
                    <div class="mt-auto flex items-center justify-between gap-2 border-t border-line-soft pt-3 text-xs text-muted">
                        <span class="truncate">{{ changed(p) }}</span>
                        <span v-if="p.canUpdate" class="inline-flex flex-none items-center gap-1 font-semibold text-navy"><Icon name="settings" :size="14" />Edit</span>
                    </div>
                </component>

                <Link :href="urls.create"
                      class="flex min-h-[160px] items-center justify-center gap-2 rounded-lg border border-dashed border-offline-border text-[13px] font-semibold text-ink-2 hover:border-navy hover:text-navy">
                    <Icon name="plus" :size="16" />Create a profile
                </Link>
            </section>
        </div>
    </PortalShell>
</template>
