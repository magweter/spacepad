<script setup>
import { computed } from 'vue';
import Icon from '../../../Components/Icon.vue';
import ProBadge from '../../../Components/Upgrade/ProBadge.vue';
import { openUpgrade } from '../../../Components/Upgrade/upgrade';

const props = defineProps({ checklist: Object, hasPro: Boolean, urls: Object });

const items = computed(() => [
    { done: props.checklist.calendar, label: 'Connect a calendar', href: props.urls.newDisplay },
    { done: props.checklist.display, label: 'Create your first display', href: props.urls.newDisplay },
    { done: props.checklist.tablet, label: 'Pair a tablet', href: props.urls.pairing },
    { done: props.checklist.teammate, label: 'Invite a teammate', href: props.urls.team, pro: !props.hasPro, feature: 'team' },
    { done: props.checklist.board, label: 'Create an overview board', href: props.urls.boardsCreate, pro: !props.hasPro, feature: 'boards' },
]);
const done = computed(() => items.value.filter((i) => i.done).length);
</script>

<template>
    <div v-if="done < items.length" class="sp-card flex flex-col gap-3 px-5 py-[18px]">
        <div class="flex items-center justify-between">
            <h2 class="font-display text-base font-bold tracking-tight">Finish setup</h2>
            <span class="text-xs text-muted">{{ done }} of {{ items.length }}</span>
        </div>
        <div class="h-1.5 overflow-hidden rounded-full bg-line"><div class="h-full bg-amber" :style="{ width: `${(done / items.length) * 100}%` }"></div></div>
        <ul class="flex flex-col gap-2 text-[13px]">
            <li v-for="item in items" :key="item.label" class="flex items-center gap-2.5" :class="item.done ? 'text-muted' : ''">
                <span v-if="item.done" class="flex h-[18px] w-[18px] flex-none items-center justify-center rounded-full bg-available-bg text-available-text"><Icon name="check" :size="11" :stroke="3" /></span>
                <span v-else class="h-[18px] w-[18px] flex-none rounded-full border-2 border-offline-border"></span>
                <span v-if="item.done" class="line-through">{{ item.label }}</span>
                <button v-else-if="item.pro" type="button" class="font-medium text-navy hover:underline" @click="openUpgrade(item.feature)">{{ item.label }}</button>
                <a v-else :href="item.href" class="font-medium text-navy hover:underline">{{ item.label }}</a>
                <ProBadge v-if="item.pro && !item.done" :feature="item.feature" class="ml-auto" />
            </li>
        </ul>
    </div>
</template>
