<script setup>
// The onboarding chrome: a navy rail with the steps and who is signed in, the page beside it.
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';
import Logo from './Logo.vue';

const props = defineProps({
    title: { type: String, required: true },
    subtitle: { type: String, default: null },
    items: { type: Array, required: true }, // { key, name, sub, number, done, current }
    user: { type: Object, required: true },
    logoutUrl: { type: String, required: true },
});

const initials = computed(() => (props.user.name || props.user.email || '?').split(/[\s.@]+/).filter(Boolean).slice(0, 2).map((p) => p[0]).join('').toUpperCase());
</script>

<template>
    <div class="flex min-h-screen flex-wrap bg-ground text-sm leading-normal text-ink">
        <aside class="flex flex-[0_0_100%] flex-col gap-8 bg-navy px-6 py-7 text-on-navy-2 md:flex-[0_0_340px] md:p-9">
            <Logo dark />
            <div class="flex flex-col gap-1.5">
                <h1 class="text-2xl leading-[1.2] font-bold tracking-tight text-white">{{ title }}</h1>
                <p v-if="subtitle" class="text-[13px] text-on-navy-muted">{{ subtitle }}</p>
            </div>
            <ol class="flex flex-col gap-1">
                <li v-for="item in items" :key="item.key" class="flex items-start gap-3 py-2.5">
                    <span class="flex h-[26px] w-[26px] flex-none items-center justify-center rounded-full border-2 text-xs font-bold"
                          :class="item.done ? 'border-amber bg-amber text-navy' : item.current ? 'border-white bg-white text-navy' : 'border-navy-600 text-on-navy-muted'">
                        {{ item.done ? '✓' : item.number }}
                    </span>
                    <span class="flex flex-col gap-px">
                        <span class="font-semibold" :class="item.current || item.done ? 'text-white' : 'text-on-navy-muted'">{{ item.name }}</span>
                        <span class="text-xs text-on-navy-muted">{{ item.sub }}</span>
                    </span>
                </li>
            </ol>
            <div class="flex-1"></div>
            <div class="flex items-center gap-2.5 border-t border-navy-700 pt-4">
                <span class="flex h-8 w-8 flex-none items-center justify-center rounded-full bg-on-navy-muted text-[13px] font-semibold text-navy">{{ initials }}</span>
                <span class="flex min-w-0 flex-1 flex-col">
                    <span class="truncate text-[13px] font-medium text-white">{{ user.name }}</span>
                    <span class="truncate text-xs text-on-navy-muted">{{ user.email }}</span>
                </span>
                <button type="button" class="rounded-md px-2 py-1 text-xs font-semibold text-on-navy-2 hover:bg-navy-800 hover:text-white" @click="router.post(logoutUrl)">Log out</button>
            </div>
        </aside>

        <main class="flex min-w-0 flex-[999_1_560px] flex-col items-center px-6 py-10 md:px-10 md:py-14">
            <div class="flex w-full max-w-[720px] flex-col gap-6">
                <slot />
            </div>
        </main>
    </div>
</template>
