<script setup>
// The display as it hangs on the wall, inside the portal: a large drawing, whether its tablet is
// online, and the way back to the overview. Nothing else to do here.
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import PortalShell from '../../Components/PortalShell.vue';
import DevicePreview from '../../Components/DevicePreview.vue';
import Icon from '../../Components/Icon.vue';
import Button from '../../DesignSystem/Button.vue';

const props = defineProps({
    shell: { type: Object, required: true },
    display: { type: Object, default: null },
    device: { type: Object, default: null },
    provider: { type: String, default: null },
    urls: { type: Object, required: true },
});

const name = computed(() => props.display?.name || 'Your display');
const online = computed(() => props.device?.online);
const time = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
</script>

<template>
    <Head :title="`${name} live`" />

    <PortalShell :shell="shell" :title="name">
        <template #actions>
            <Button variant="secondary" :href="urls.dashboard"><template #icon><Icon name="arrowLeft" :size="16" /></template>Back to overview</Button>
        </template>

        <div class="flex flex-col items-center gap-4 px-6 pt-10 pb-12 md:px-8">
            <div class="w-full max-w-[1040px]">
                <DevicePreview :room="name" :time="time" meta="" :scale="2" />
            </div>
            <span class="inline-flex items-center gap-2 text-[13px] text-muted">
                <span class="sp-dot sp-dot-sm" :class="online ? 'sp-dot-available' : 'sp-dot-offline'"></span>
                <template v-if="device">{{ device.name || 'Tablet' }}, {{ online ? 'online' : 'offline' }}<template v-if="provider">, synced with {{ provider }}</template></template>
                <template v-else>No tablet paired yet</template>
            </span>
        </div>
    </PortalShell>
</template>
