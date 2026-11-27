<script setup>
// Where to go once the display exists, at the bottom of the pairing step: add another or customise
// it, and the overview as the primary way out. Leaving without a tablet is
// fine; the connect code stays in the portal.
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import ProBadge from '../../../Components/Upgrade/ProBadge.vue';
import { openUpgrade } from '../../../Components/Upgrade/upgrade';
import Icon from '../../../Components/Icon.vue';

const props = defineProps({ display: Object, paired: Boolean, urls: Object });
const emit = defineEmits(['leave']);

const upgrade = computed(() => usePage().props.upgrade);
const lockedDisplay = computed(() => upgrade.value?.mustUpgradeForDisplay);
const lockedConfigure = computed(() => upgrade.value && !upgrade.value.hasPro);

const card = 'flex h-full w-full cursor-pointer flex-col gap-0.5 rounded-lg border border-line bg-surface px-4 py-3.5 text-left transition-colors hover:border-navy-400 hover:bg-surface-raised';
</script>

<template>
    <section aria-label="What next" class="flex flex-col gap-2.5">
        <h2 class="text-[13px] font-semibold">{{ paired ? 'What next?' : 'Or carry on without a tablet' }}</h2>
        <div class="grid gap-2.5 sm:grid-cols-2">

            <button v-if="lockedDisplay" type="button" :class="card" @click="openUpgrade('displays')">
                <span class="flex items-center justify-between gap-3"><span class="font-semibold">Add another display</span><ProBadge feature="displays" /></span>
                <span class="text-xs text-muted">Same steps, calendar already connected.</span>
            </button>
            <a v-else :href="urls.step + '?step=room&new=1'" :class="card" @click="emit('leave')">
                <span class="font-semibold">Add another display</span>
                <span class="text-xs text-muted">Same steps, calendar already connected.</span>
            </a>

            <template v-if="urls.configure">
                <button v-if="lockedConfigure" type="button" :class="card" @click="openUpgrade('configure')">
                    <span class="flex items-center justify-between gap-3"><span class="font-semibold">Customise the display</span><ProBadge feature="configure" /></span>
                    <span class="text-xs text-muted">Labels, check-in, theme, logo.</span>
                </button>
                <a v-else :href="urls.configure" :class="card" @click="emit('leave')">
                    <span class="font-semibold">Customise the display</span>
                    <span class="text-xs text-muted">Labels, check-in, theme, logo.</span>
                </a>
            </template>
        </div>
        <!-- The way out of the setup, and the one most people take. -->
        <div class="mt-2 flex flex-wrap items-center justify-end gap-3">
            <span v-if="!paired" class="text-xs text-muted">The connect code stays on the overview, so you can pair a tablet later.</span>
            <a :href="urls.dashboard" class="sp-btn sp-btn-primary sp-btn-md" @click="emit('leave')">Go to the overview<Icon name="arrowRight" :size="16" /></a>
        </div>
    </section>
</template>
