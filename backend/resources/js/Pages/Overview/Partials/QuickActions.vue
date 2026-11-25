<script setup>
// What people come to the portal for most, one click away instead of a search through the menu.
// Pro actions open the upgrade dialog on a free workspace, as in Finish setup.
import { computed } from 'vue';
import Icon from '../../../Components/Icon.vue';
import ProBadge from '../../../Components/Upgrade/ProBadge.vue';
import { openUpgrade } from '../../../Components/Upgrade/upgrade';

const props = defineProps({ hasPro: Boolean, canBook: Boolean, urls: Object });
const emit = defineEmits(['book']);

const actions = computed(() => [
    props.canBook && { label: 'Book a room', icon: 'plus', click: () => emit('book') },
    { label: 'Pair a tablet', icon: 'tablet', href: props.urls.pairing },
    { label: 'Change display settings', icon: 'settings', href: props.urls.displays },
    { label: 'Connect a calendar', icon: 'calendars', href: props.urls.calendars },
    { label: 'Invite a teammate', icon: 'team', href: props.urls.team, pro: !props.hasPro, feature: 'team' },
    { label: 'Create a board', icon: 'boards', href: props.urls.boardsCreate, pro: !props.hasPro, feature: 'boards' },
].filter(Boolean));

const item = 'flex w-full items-center gap-2.5 rounded-lg px-2.5 py-2 text-left text-[13px] font-medium text-ink hover:bg-surface-raised';
</script>

<template>
    <nav aria-label="Quick actions" class="sp-card flex flex-col gap-1.5 px-3 py-3.5">
        <h2 class="px-2.5 pb-1 font-display text-base font-bold tracking-tight">Quick actions</h2>
        <template v-for="a in actions" :key="a.label">
            <button v-if="a.click || a.pro" type="button" :class="item" @click="a.pro ? openUpgrade(a.feature) : a.click()">
                <span class="grid h-7 w-7 flex-none place-items-center rounded-lg bg-navy-50 text-navy"><Icon :name="a.icon" :size="15" /></span>
                {{ a.label }}
                <ProBadge v-if="a.pro" :feature="a.feature" class="ml-auto" />
            </button>
            <a v-else :href="a.href" :class="item">
                <span class="grid h-7 w-7 flex-none place-items-center rounded-lg bg-navy-50 text-navy"><Icon :name="a.icon" :size="15" /></span>
                {{ a.label }}
            </a>
        </template>
    </nav>
</template>
