<script setup>
// The portal shell from the design: a navy sidebar that never changes, the page beside it. Only
// the display setup uses it so far; its nav links to the existing (Blade) pages.
import Logo from './Logo.vue';

defineProps({ shell: { type: Object, required: true } });

const initials = (name) => (name || '?').split(' ').map((p) => p[0]).slice(0, 2).join('').toUpperCase();
</script>

<template>
    <div class="flex min-h-screen flex-wrap bg-ground text-sm leading-normal text-ink">
        <aside class="flex flex-[0_0_100%] flex-col gap-6 bg-navy px-3.5 py-5 text-on-navy-2 md:w-[248px] md:flex-[0_0_248px]">
            <Logo :href="shell.nav[0].href" dark small class="px-1.5 pt-0.5 pb-1.5" />
            <div class="flex items-center gap-2.5 rounded-[10px] border border-navy-700 bg-navy-800 px-3 py-2.5 text-white">
                <span class="flex h-8 w-8 flex-none items-center justify-center rounded-md bg-amber text-[15px] font-bold text-navy">{{ initials(shell.workspace) }}</span>
                <span class="flex min-w-0 flex-col">
                    <span class="truncate font-semibold">{{ shell.workspace }}</span>
                    <span class="text-xs text-on-navy-muted">{{ shell.plan }}</span>
                </span>
            </div>
            <nav aria-label="Main" class="flex flex-col gap-0.5">
                <template v-for="item in shell.nav" :key="item.label">
                    <div v-if="item.divider" class="mx-1 my-2.5 h-px bg-navy-700"></div>
                    <a :href="item.href" :class="['sp-nav', { 'sp-nav-active': item.active }]" :aria-current="item.active ? 'page' : null">
                        <span class="sp-nav-label">{{ item.label }}</span>
                    </a>
                </template>
            </nav>
            <div class="flex-1"></div>
            <div class="flex items-center gap-2.5 px-1.5 py-1">
                <span class="flex h-8 w-8 items-center justify-center rounded-full bg-on-navy-muted text-[13px] font-semibold text-navy">{{ initials(shell.user) }}</span>
                <span class="flex min-w-0 flex-col">
                    <span class="truncate text-[13px] font-medium text-white">{{ shell.user }}</span>
                    <span v-if="shell.role" class="text-xs text-on-navy-muted">{{ shell.role }}</span>
                </span>
            </div>
        </aside>

        <main class="flex min-w-0 flex-[999_1_560px] flex-col">
            <slot />
        </main>
    </div>
</template>
