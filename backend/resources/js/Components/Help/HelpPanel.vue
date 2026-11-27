<script setup>
// The Help & Roadmap slide-over. Opened with openHelp('help' | 'roadmap') from helpPanel.js; its
// data (FAQ, roadmap with your votes) loads from help.data the first time it opens.
import { onBeforeUnmount, onMounted, ref } from 'vue';
import HelpIcon from './HelpIcon.vue';
import HelpTab from './HelpTab.vue';
import RoadmapTab from './RoadmapTab.vue';
import Button from '../../DesignSystem/Button.vue';
import { OPEN_EVENT, helpUrls, requestJson } from './helpPanel.js';

const open = ref(false);
const tab = ref('help');
const loading = ref(false);
const failed = ref(false);
const data = ref(null);

async function load() {
    if (loading.value) return;
    loading.value = true;
    failed.value = false;
    try {
        const result = await requestJson(helpUrls.data);
        if (!result.ok) throw new Error('Help data failed to load');
        data.value = result.data;
    } catch {
        failed.value = true;
    } finally {
        loading.value = false;
    }
}

function show(event) {
    const requested = event?.detail?.tab;
    if (requested === 'help' || requested === 'roadmap') tab.value = requested;
    open.value = true;
    if (!data.value) load();
}

const close = () => { open.value = false; };
const onKey = (e) => { if (e.key === 'Escape' && open.value) close(); };

onMounted(() => {
    window.addEventListener(OPEN_EVENT, show);
    window.addEventListener('keydown', onKey);
});
onBeforeUnmount(() => {
    window.removeEventListener(OPEN_EVENT, show);
    window.removeEventListener('keydown', onKey);
});

const tabs = [
    { key: 'help', label: 'Help' },
    { key: 'roadmap', label: 'Roadmap and requests' },
];
</script>

<template>
    <Teleport to="body">
        <Transition enter-active-class="transition-opacity duration-200" enter-from-class="opacity-0" leave-active-class="transition-opacity duration-150" leave-to-class="opacity-0">
            <button v-if="open" type="button" aria-label="Close help" class="fixed inset-0 z-40 cursor-default bg-navy/45" @click="close"></button>
        </Transition>

        <Transition enter-active-class="transition-transform duration-300 ease-out" enter-from-class="translate-x-full" leave-active-class="transition-transform duration-200 ease-in" leave-to-class="translate-x-full">
            <aside v-if="open" role="dialog" aria-modal="true" aria-labelledby="help-panel-title"
                   class="fixed inset-y-0 right-0 z-50 flex w-full flex-col bg-ground text-sm leading-normal text-ink shadow-2xl sm:max-w-[600px]">
                <header class="flex-none border-b border-line bg-surface">
                    <div class="flex items-center justify-between gap-4 px-6 pt-5">
                        <h2 id="help-panel-title" class="font-display text-lg font-bold tracking-tight">Help and roadmap</h2>
                        <button type="button" aria-label="Close" class="rounded-md p-1.5 text-muted hover:bg-surface-raised hover:text-ink" @click="close">
                            <HelpIcon name="close" />
                        </button>
                    </div>
                    <nav role="tablist" aria-label="Help sections" class="mt-3 flex gap-5 px-6">
                        <button v-for="item in tabs" :key="item.key" type="button" role="tab" :aria-selected="tab === item.key" @click="tab = item.key"
                                :class="['-mb-px flex items-center gap-1.5 border-b-2 pb-3 text-[13px] font-semibold transition-colors',
                                         tab === item.key ? 'border-amber text-ink' : 'border-transparent text-muted hover:text-ink-2']">
                            {{ item.label }}
                            <span v-if="item.key === 'roadmap' && data?.roadmap?.length" class="rounded-md bg-surface-raised px-1.5 text-[11px] font-semibold text-ink-3">{{ data.roadmap.length }}</span>
                        </button>
                    </nav>
                </header>

                <div v-if="!data && loading" class="flex flex-1 items-center justify-center text-muted" role="status">Loading...</div>

                <div v-else-if="!data && failed" class="flex flex-1 flex-col items-center justify-center gap-3 px-6 text-center">
                    <p class="font-semibold">Help could not be loaded.</p>
                    <Button variant="secondary" size="sm" @click="load">Try again</Button>
                </div>

                <template v-else-if="data">
                    <HelpTab v-show="tab === 'help'" :faqs="data.faqs" :categories="data.categories" :email="data.email" />
                    <RoadmapTab v-show="tab === 'roadmap'" :items="data.roadmap" />
                </template>
            </aside>
        </Transition>
    </Teleport>
</template>
