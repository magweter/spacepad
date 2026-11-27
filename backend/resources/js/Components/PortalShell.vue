<script setup>
// The portal shell from the design: a navy sidebar that never changes, the page beside it. Pages
// that pass a title get the white header bar with their actions on the right.
import { nextTick, ref } from 'vue';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import Logo from './Logo.vue';
import Icon from './Icon.vue';
import ProBadge from './Upgrade/ProBadge.vue';
import UpgradeModal from './Upgrade/UpgradeModal.vue';
import HelpPanel from './Help/HelpPanel.vue';
import ToastHost from './Toasts/ToastHost.vue';
import AlertPopup from './AlertPopup.vue';
import HelpIcon from './Help/HelpIcon.vue';
import { openHelp } from './Help/helpPanel.js';

// Pro is marked in the sidebar only, and only for a workspace without Pro.
const props = defineProps({
    shell: { type: Object, required: true },
    title: { type: String, default: null },
    subtitle: { type: String, default: null },
});

const switching = ref(false);
const page = usePage();
const initials = (name) => (name || '?').split(' ').map((p) => p[0]).slice(0, 2).join('').toUpperCase();

// Creating a workspace from the switcher: a name, then it opens straight away (the server switches to it).
const creating = ref(false);
const newName = ref(null);
const newWorkspace = useForm({ name: '' });
async function startCreating() {
    creating.value = true;
    await nextTick();
    newName.value?.focus();
}
function createWorkspace() {
    newWorkspace.post(props.shell.createWorkspaceUrl, {
        onSuccess: () => { newWorkspace.reset(); creating.value = false; switching.value = false; },
    });
}

function switchTo(workspace) {
    switching.value = false;
    if (!workspace.current) router.post(props.shell.switchUrl, { workspace_id: workspace.id });
}
</script>

<template>
    <!-- Design 01d: a white sidebar, the page beside it as a light panel with rounded corners. -->
    <div class="flex min-h-screen flex-wrap bg-surface text-sm leading-normal text-ink">
        <aside class="flex flex-[0_0_100%] flex-col gap-6 bg-surface px-4 pt-6 pb-5 text-ink-3 md:sticky md:top-0 md:h-screen md:w-[248px] md:flex-[0_0_248px] md:overflow-y-auto">
            <Logo :href="shell.nav[0].href" class="px-1.5 pb-1.5" />

            <div class="relative">
                <button type="button" aria-label="Switch workspace" :aria-expanded="switching" @click="switching = !switching"
                        class="flex w-full items-center gap-2.5 rounded-[10px] border border-line-soft bg-ground px-3 py-2.5 text-left text-navy hover:border-line">
                    <span class="flex h-8 w-8 flex-none items-center justify-center rounded-md bg-amber text-[15px] font-bold text-navy">{{ initials(shell.workspace) }}</span>
                    <span class="flex min-w-0 flex-1 flex-col">
                        <span class="truncate font-semibold">{{ shell.workspace }}</span>
                        <span class="text-xs text-muted">{{ shell.plan }}</span>
                    </span>
                    <Icon name="chevrons" :size="16" />
                </button>
                <div v-if="switching" class="absolute inset-x-0 top-full z-20 mt-1.5 flex flex-col overflow-hidden rounded-[10px] border border-line bg-surface py-1 text-ink shadow-lg">
                    <button v-for="workspace in shell.workspaces" :key="workspace.id" type="button" @click="switchTo(workspace)"
                            class="flex items-center justify-between gap-2 px-3 py-2 text-left hover:bg-surface-raised" :class="workspace.current ? 'font-semibold' : ''">
                        <span class="truncate">{{ workspace.name }}</span>
                        <Icon v-if="workspace.current" name="check" :size="14" class="text-available" />
                    </button>
                    <div class="mt-1 border-t border-line-soft pt-1">
                        <button v-if="!creating" type="button" class="flex w-full items-center gap-2 px-3 py-2 text-left font-semibold text-navy hover:bg-surface-raised" @click="startCreating">
                            <Icon name="plus" :size="14" :stroke="2.5" />Create workspace
                        </button>
                        <form v-else class="flex flex-col gap-2 px-3 py-2" @submit.prevent="createWorkspace">
                            <label for="new-workspace-name" class="text-xs font-semibold text-ink-2">Name of the new workspace</label>
                            <input id="new-workspace-name" ref="newName" v-model="newWorkspace.name" type="text" required maxlength="255" placeholder="Acme HQ"
                                   class="w-full rounded-md border border-line bg-surface px-2.5 py-1.5 text-[13px] text-ink focus:border-navy focus:outline-none">
                            <span v-if="newWorkspace.errors.name" class="text-xs text-reserved-text">{{ newWorkspace.errors.name }}</span>
                            <span class="flex justify-end gap-2">
                                <button type="button" class="sp-btn sp-btn-ghost sp-btn-sm" @click="creating = false">Cancel</button>
                                <button type="submit" class="sp-btn sp-btn-primary sp-btn-sm" :disabled="newWorkspace.processing || !newWorkspace.name.trim()">Create</button>
                            </span>
                        </form>
                    </div>
                </div>
            </div>

            <nav aria-label="Main" class="flex flex-col gap-0.5">
                <template v-for="item in shell.nav" :key="item.key">
                    <div v-if="item.divider" class="mx-1 my-2.5 h-px bg-line-soft"></div>
                    <!-- Inertia links: switching pages swaps the page, not the whole app, and hovering loads it ahead. -->
                    <Link :href="item.href" prefetch :cache-for="'10s'" :class="['sp-nav', { 'sp-nav-active': item.active }]" :aria-current="item.active ? 'page' : null">
                        <span class="sp-nav-icon"><Icon :name="item.icon" /></span>
                        <span class="sp-nav-label">{{ item.label }}</span>
                        <span v-if="item.new" class="sp-chip sp-chip-available rounded-[5px] px-1.5 py-px text-[11px] leading-none">New</span>
                        <span v-else-if="item.count" class="sp-nav-count">{{ item.count }}</span>
                        <ProBadge v-else-if="item.pro" :feature="item.key" />
                    </Link>
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
                <span class="flex h-8 w-8 flex-none items-center justify-center rounded-full bg-navy text-[13px] font-semibold text-white">{{ initials(shell.user) }}</span>
                <Link :href="shell.account" prefetch class="flex min-w-0 flex-1 flex-col rounded-md hover:opacity-80" :aria-current="shell.accountActive ? 'page' : null" title="Your account">
                    <span class="truncate text-[13px] font-medium text-navy">{{ shell.user }}</span>
                    <span v-if="shell.role" class="text-xs text-muted">{{ shell.role }}</span>
                </Link>
                <button type="button" class="rounded-md px-2 py-1 text-xs font-semibold text-muted hover:bg-ground hover:text-navy" @click="router.post(shell.logout)">Log out</button>
            </div>
        </aside>

        <main class="flex min-w-0 flex-[999_1_560px] flex-col bg-ground md:my-4 md:mr-4 md:rounded-2xl">
            <!-- The title with its subtitle right under it; the page's actions on the right, level with both. -->
            <header v-if="title" class="flex flex-wrap items-center gap-x-6 gap-y-3 px-6 pt-7 md:px-10 md:pt-9">
                <div class="flex min-w-0 flex-col gap-1">
                    <h1 class="flex items-center gap-2 font-display text-[28px] leading-tight font-bold tracking-tight text-navy">{{ title }}</h1>
                    <p v-if="subtitle || $slots.subtitle" class="max-w-[720px] text-[13px] text-muted"><slot name="subtitle">{{ subtitle }}</slot></p>
                </div>
                <div v-if="$slots.actions" class="ml-auto flex flex-wrap items-center gap-3"><slot name="actions" /></div>
            </header>
            <slot />
        </main>

        <HelpPanel v-if="!page.props.app?.selfHosted" />
        <UpgradeModal />
        <ToastHost />
        <AlertPopup />
    </div>
</template>
