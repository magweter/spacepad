<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import StepHeading from '../../../Components/StepHeading.vue';
import DevicePreview from '../../../Components/DevicePreview.vue';
import ProBadge from '../../../Components/Upgrade/ProBadge.vue';
import { openUpgrade } from '../../../Components/Upgrade/upgrade';
import Chip from '../../../DesignSystem/Chip.vue';

defineOptions({ inheritAttrs: false });

const props = defineProps({ display: Object, device: Object, urls: Object, eyebrow: String, flow: Object });
const time = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

const upgrade = computed(() => usePage().props.upgrade);
const lockedDisplay = computed(() => upgrade.value?.mustUpgradeForDisplay);
const lockedConfigure = computed(() => upgrade.value && !upgrade.value.hasPro);

const card = 'flex h-full w-full cursor-pointer flex-col gap-0.5 rounded-lg border border-line bg-surface px-4 py-3.5 text-left transition-colors hover:border-navy-400 hover:bg-surface-raised';
</script>

<template>
    <StepHeading :eyebrow="eyebrow" :title="display && device ? `${display.name} is on the wall.` : 'The display is ready. Pair a tablet whenever you like.'">
        <template v-if="display && device">It reads the room calendar in real time and lets people book from the tablet.</template>
        <template v-else>The connect code stays in the portal, so you can pair a tablet later.</template>
    </StepHeading>

    <DevicePreview :room="display?.name || 'Your room'" :time="time" meta="" :scale="1.6" />

    <div class="grid gap-2.5 sm:grid-cols-2">
        <a v-if="display && device" :href="urls.live" :class="card">
            <span class="flex items-center justify-between gap-3"><span class="font-semibold">See {{ display.name }} live</span><Chip tone="navy">Recommended</Chip></span>
            <span class="text-xs text-muted">The display as it hangs on the wall.</span>
        </a>

        <button v-if="lockedDisplay" type="button" :class="card" @click="openUpgrade('displays')">
            <span class="flex items-center justify-between gap-3"><span class="font-semibold">Add another display</span><ProBadge feature="displays" /></span>
            <span class="text-xs text-muted">Same steps, calendar already connected.</span>
        </button>
        <a v-else :href="urls.step + '?step=room&new=1'" :class="card">
            <span class="font-semibold">Add another display</span>
            <span class="text-xs text-muted">Same steps, calendar already connected.</span>
        </a>

        <template v-if="urls.configure">
            <button v-if="lockedConfigure" type="button" :class="card" @click="openUpgrade('configure')">
                <span class="flex items-center justify-between gap-3"><span class="font-semibold">Customise the display</span><ProBadge feature="configure" /></span>
                <span class="text-xs text-muted">Labels, check-in, theme, logo.</span>
            </button>
            <a v-else :href="urls.configure" :class="card">
                <span class="font-semibold">Customise the display</span>
                <span class="text-xs text-muted">Labels, check-in, theme, logo.</span>
            </a>
        </template>

        <a :href="urls.dashboard" :class="card">
            <span class="font-semibold">Go to the overview</span>
            <span class="text-xs text-muted">Everything else lives there.</span>
        </a>
    </div>
</template>
