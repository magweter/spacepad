<script setup>
// Branding: font, text sizes, logo and background. Uploads ride along with the section's save.
import { computed } from 'vue';
import FieldRow from './FieldRow.vue';
import ImagePicker from './ImagePicker.vue';
import { inputClass } from './fields';

const props = defineProps({
    form: { type: Object, required: true },
    images: { type: Object, required: true },
    options: { type: Object, required: true },
    inheritedFrom: { type: String, default: null },
});

// Picking a bundled background replaces an upload that was waiting, and the other way round.
function pickDefault(key) {
    props.form.default_background = props.form.default_background === key ? null : key;
    if (props.form.default_background) {
        props.form.background_image = null;
        props.form.remove_background_image = false;
    }
}
function pickBackground(file) {
    props.form.background_image = file;
    if (file) props.form.default_background = null;
}
const backgroundUrl = computed(() => (props.form.default_background
    ? props.options.backgrounds.find((b) => b.key === props.form.default_background)?.url
    : props.images.background.url));
const isCurrent = (key) => props.form.default_background === key
    || (!props.form.default_background && !props.form.background_image && !props.form.remove_background_image && props.images.background.default === key);
</script>

<template>
    <div class="flex flex-col gap-3">
        <FieldRow label="Font" for="font_family" :error="form.errors.font_family">
            <select id="font_family" v-model="form.font_family" :class="inputClass">
                <option v-for="f in options.fonts" :key="f" :value="f">{{ f }}</option>
            </select>
        </FieldRow>

        <div class="flex flex-col gap-2 border-y border-line-soft py-3">
            <span class="text-[13px] font-medium">Text sizes</span>
            <span class="text-xs text-muted">Larger text reads from further away; a long meeting title is shortened sooner.</span>
            <div class="grid grid-cols-1 gap-x-4 gap-y-2.5 sm:grid-cols-2">
                <label v-for="el in options.fontSizeElements" :key="el.key" class="flex flex-col gap-1">
                    <span class="text-xs text-ink-3">{{ el.label }}</span>
                    <select v-model="form[`font_size_${el.key}`]" :class="inputClass">
                        <option v-for="s in options.fontSizes" :key="s.value" :value="s.value">{{ s.label }}</option>
                    </select>
                </label>
            </div>
        </div>

        <ImagePicker id="logo" label="Logo" :url="images.logo.url" :own="images.logo.own" :inherited-from="inheritedFrom"
                     v-model:file="form.logo" v-model:remove="form.remove_logo" :error="form.errors.logo"
                     hint="PNG, JPG or GIF, up to 2 MB. Around 200 by 100 pixels works well." />

        <ImagePicker id="background_image" label="Background" cover :url="backgroundUrl" :own="images.background.own"
                     :inherited-from="inheritedFrom" :file="form.background_image" @update:file="pickBackground"
                     v-model:remove="form.remove_background_image" :error="form.errors.background_image"
                     hint="Your own image, 1920 by 1080 pixels, or one of the backgrounds below. Keep it calm so the status stays readable." />

        <div class="flex flex-wrap items-start gap-x-3.5 gap-y-1.5">
            <span class="flex-[0_0_150px] text-[13px] font-medium">Bundled backgrounds</span>
            <div class="grid min-w-0 flex-[1_1_200px] grid-cols-4 gap-2">
                <button v-for="bg in options.backgrounds" :key="bg.key" type="button" :aria-pressed="isCurrent(bg.key) ? 'true' : 'false'" :aria-label="`Background ${bg.key.replace('default_', '')}`"
                        class="aspect-[16/10] overflow-hidden rounded-md border-2 transition-colors"
                        :class="isCurrent(bg.key) ? 'border-navy ring-2 ring-navy/30' : 'border-line hover:border-navy-400'"
                        @click="pickDefault(bg.key)">
                    <img :src="bg.url" alt="" class="h-full w-full object-cover">
                </button>
            </div>
        </div>
    </div>
</template>
