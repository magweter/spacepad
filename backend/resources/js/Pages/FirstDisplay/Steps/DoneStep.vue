<script setup>
import StepHeading from '../../../Components/StepHeading.vue';
import DevicePreview from '../../../Components/DevicePreview.vue';
import Card from '../../../DesignSystem/Card.vue';
import Chip from '../../../DesignSystem/Chip.vue';

defineOptions({ inheritAttrs: false });

const props = defineProps({ display: Object, device: Object, urls: Object, eyebrow: String, flow: Object });
const time = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
</script>

<template>
    <StepHeading :eyebrow="eyebrow" :title="display && device ? `${display.name} is on the wall.` : 'The display is ready. Pair a tablet whenever you like.'">
        <template v-if="display && device">It reads the room calendar in real time and lets people book from the tablet.</template>
        <template v-else>The connect code stays in the portal, so you can pair a tablet later.</template>
    </StepHeading>

    <div class="grid gap-6 lg:grid-cols-[1.1fr_1fr] lg:items-start">
        <DevicePreview :room="display?.name || 'Your room'" :time="time" meta="" />

        <div class="flex flex-col gap-2.5">
            <a v-if="display && device" :href="urls.live" class="block">
                <Card interactive>
                    <span class="flex items-center justify-between gap-3">
                        <span class="flex flex-col"><span class="font-semibold">See {{ display.name }} live</span><span class="text-xs text-muted">And what most teams do next.</span></span>
                        <Chip tone="navy">Recommended</Chip>
                    </span>
                </Card>
            </a>
            <a :href="urls.step + '?step=room&new=1'" class="block">
                <Card interactive><span class="flex flex-col"><span class="font-semibold">Add another display</span><span class="text-xs text-muted">Same steps, calendar already connected.</span></span></Card>
            </a>
            <a v-if="urls.configure" :href="urls.configure" class="block">
                <Card interactive><span class="flex flex-col"><span class="font-semibold">Customise the display</span><span class="text-xs text-muted">Labels, check-in, theme, logo.</span></span></Card>
            </a>
            <a :href="urls.dashboard" class="block">
                <Card interactive><span class="flex flex-col"><span class="font-semibold">Go to the overview</span><span class="text-xs text-muted">Everything else lives there.</span></span></Card>
            </a>
        </div>
    </div>
</template>
