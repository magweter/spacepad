<script setup>
// Advertisement: an image over the right half of the screen at a set interval.
import SettingRow from '../../../DesignSystem/SettingRow.vue';
import Toggle from '../../../DesignSystem/Toggle.vue';
import ImagePicker from './ImagePicker.vue';
import { numberClass } from './fields';

defineProps({
    form: { type: Object, required: true },
    images: { type: Object, required: true },
    inheritedFrom: { type: String, default: null },
    durationMax: { type: Number, default: 300 },
});
</script>

<template>
    <div class="flex flex-col gap-3">
        <SettingRow label="Show the advertisement" help="The room status stays visible on the left; a tap closes the image early.">
            <Toggle v-model="form.advertisement_enabled" label="Show the advertisement" />
        </SettingRow>
        <div class="flex flex-col gap-3" :class="{ 'pointer-events-none opacity-50': !form.advertisement_enabled }" :aria-disabled="!form.advertisement_enabled">
            <ImagePicker id="advertisement_image" label="Image" cover :url="images.advertisement.url" :own="images.advertisement.own" :inherited-from="inheritedFrom"
                         v-model:file="form.advertisement_image" v-model:remove="form.remove_advertisement_image" :error="form.errors.advertisement_image"
                         hint="PNG, JPG or GIF, up to 4 MB. 960 by 1080 pixels fills the right half." />
            <div class="grid grid-cols-1 gap-3.5 sm:grid-cols-2">
                <div class="flex flex-col gap-1.5">
                    <label for="advertisement_interval" class="text-[13px] font-medium">Show every</label>
                    <span class="flex items-center gap-2"><input id="advertisement_interval" v-model.number="form.advertisement_interval" type="number" min="1" max="60" :class="numberClass"><span class="text-[13px] text-muted">minutes</span></span>
                    <span v-if="form.errors.advertisement_interval" class="text-xs font-medium text-reserved-text">{{ form.errors.advertisement_interval }}</span>
                </div>
                <div class="flex flex-col gap-1.5">
                    <label for="advertisement_duration" class="text-[13px] font-medium">Show for</label>
                    <span class="flex items-center gap-2"><input id="advertisement_duration" v-model.number="form.advertisement_duration" type="number" min="5" :max="durationMax" :class="numberClass"><span class="text-[13px] text-muted">seconds</span></span>
                    <span v-if="form.errors.advertisement_duration" class="text-xs font-medium text-reserved-text">{{ form.errors.advertisement_duration }}</span>
                </div>
            </div>
        </div>
    </div>
</template>
