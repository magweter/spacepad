<script setup>
// The portal shell from the design: a navy sidebar that never changes, the page beside it. Pages
// that pass a title get the white header bar with their actions on the right.
import { ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import Logo from './Logo.vue';
import Icon from './Icon.vue';
import HelpPanel from './Help/HelpPanel.vue';
import HelpIcon from './Help/HelpIcon.vue';
import { openHelp } from './Help/helpPanel.js';

// `pro` puts the Pro chip right next to the title, for pages that are part of Pro.
const props = defineProps({ shell: { type: Object, required: true }, title: { type: String, default: null }, pro: { type: Boolean, default: false } });

const switching = ref(false);
const page = usePage();
const initials = (name) => (name || '?').split(' ').map((p) => p[0]).slice(0, 2).join('').toUpperCase();

function switchTo(workspace) {
    switching.value = false;
    if (!workspace.current) router.post(props.shell.switchUrl, { workspace_id: workspace.id });
}
</script>

<template>
    <div class="flex min-h-screen flex-wrap bg-ground text-sm leading-normal text-ink">
        <aside class="flex flex-[0_0_100%] flex-col gap-6 bg-navy px-3.5 py-5 text-on-navy-2 md:sticky md:top-0 md:h-screen md:w-[248px] md:flex-[0_0_248px] md:overflow-y-auto">
            <Logo :href="shell.nav[0].href" dark small class="px-1.5 pt-0.5 pb-1.5" />

            <div class="relative">
                <button type="button" aria-label="Switch workspace" :aria-expanded="switching" @click="switching = !switching"
                        class="flex w-full items-center gap-2.5 rounded-[10px] border border-navy-700 bg-navy-800 px-3 py-2.5 text-left text-white">
                    <span class="flex h-8 w-8 flex-none items-center justify-center rounded-md bg-amber text-[15px] font-bold text-navy">{{ initials(shell.workspace) }}</span>
                    <span class="flex min-w-0 flex-1 flex-col">
                        <span class="truncate font-semibold">{{ shell.workspace }}</span>
                        <span class="text-xs text-on-navy-muted">{{ shell.plan }}</span>
                    </span>
                    <Icon name="chevrons" :size="16" />
                </button>
                <div v-if="switching" class="absolute inset-x-0 top-full z-20 mt-1.5 flex flex-col overflow-hidden rounded-[10px] border border-line bg-surface py-1 text-ink shadow-lg">
                    <button v-for="workspace in shell.workspaces" :key="workspace.id" type="button" @click="switchTo(workspace)"
                            class="flex items-center justify-between gap-2 px-3 py-2 text-left hover:bg-surface-raised" :class="workspace.current ? 'font-semibold' : ''">
                        <span class="truncate">{{ workspace.name }}</span>
                        <Icon v-if="workspace.current" name="check" :size="14" class="text-available" />
                    </button>
                </div>
            </div>

            <nav aria-label="Main" class="flex flex-col gap-0.5">
                <template v-for="item in shell.nav" :key="item.key">
                    <div v-if="item.divider" class="mx-1 my-2.5 h-px bg-navy-700"></div>
                    <a :href="item.href" :class="['sp-nav', { 'sp-nav-active': item.active }]" :aria-current="item.active ? 'page' : null">
                        <span class="sp-nav-icon"><Icon :name="item.icon" /></span>
                        <span class="sp-nav-label">{{ item.label }}</span>
                        <span v-if="item.count" class="sp-nav-count">{{ item.count }}</span>
                        <span v-else-if="item.pro" class="rounded-md border border-info-border bg-info-bg px-1.5 py-px text-[11px] font-semibold text-info">Pro</span>
                    </a>
                </template>
            </nav>

            <div class="flex-1"></div>

            <div v-if="!page.props.app?.selfHosted" class="flex flex-col gap-0.5">
                <button type="button" class="sp-nav text-left text-[13px]" @click="openHelp('help')">
                    <span class="sp-nav-icon"><HelpIcon name="help" /></span>
                    <span class="sp-nav-label">Need help?</span>
                </button>
                <button type="button" class="sp-nav text-left text-[13px]" @click="openHelp('roadmap')">
                    <span class="sp-nav-icon"><HelpIcon name="lightbulb" /></span>
                    <span class="sp-nav-label">Make a request</span>
                </button>
            </div>

            <div class="flex items-center gap-2.5 px-1.5 py-1">
                <span class="flex h-8 w-8 flex-none items-center justify-center rounded-full bg-on-navy-muted text-[13px] font-semibold text-navy">{{ initials(shell.user) }}</span>
                <a :href="shell.account" class="flex min-w-0 flex-1 flex-col rounded-md hover:opacity-80" :aria-current="shell.accountActive ? 'page' : null" title="Your account">
                    <span class="truncate text-[13px] font-medium text-white">{{ shell.user }}</span>
                    <span v-if="shell.role" class="text-xs text-on-navy-muted">{{ shell.role }}</span>
                </a>
                <button type="button" class="rounded-md px-2 py-1 text-xs font-semibold text-on-navy-2 hover:bg-navy-800 hover:text-white" @click="router.post(shell.logout)">Log out</button>
            </div>
        </aside>

        <main class="flex min-w-0 flex-[999_1_560px] flex-col">
            <header v-if="title" class="flex flex-wrap items-center gap-4 border-b border-line bg-surface px-6 py-3.5 md:px-8">
                <h1 class="flex items-center gap-2 font-display text-[22px] font-bold tracking-tight">{{ title }}<span v-if="pro" class="rounded-md border border-info-border bg-info-bg px-1.5 py-px font-sans text-[11px] font-semibold tracking-normal text-info">Pro</span></h1>
                <div class="ml-auto flex flex-wrap items-center gap-3"><slot name="actions" /></div>
            </header>
            <slot />
        </main>

        <HelpPanel v-if="!page.props.app?.selfHosted" />
    </div>
</template>
