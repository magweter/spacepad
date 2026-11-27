<script setup>
// Create or edit a profile: the same sections as a display, saved together. Displays that follow
// the profile pick up a saved change in every section they have not given their own values.
import { computed, ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import PortalShell from '../../Components/PortalShell.vue';
import Button from '../../DesignSystem/Button.vue';
import Chip from '../../DesignSystem/Chip.vue';
import Modal from '../../DesignSystem/Modal.vue';
import Notice from '../../DesignSystem/Notice.vue';
import SectionCard from '../Configure/Partials/SectionCard.vue';
import SectionNav from '../Configure/Partials/SectionNav.vue';
import FieldRow from '../Configure/Partials/FieldRow.vue';
import TabletPreview from '../Configure/Partials/TabletPreview.vue';
import BehaviorFields from '../Configure/Partials/BehaviorFields.vue';
import DisplayFields from '../Configure/Partials/DisplayFields.vue';
import TextsFields from '../Configure/Partials/TextsFields.vue';
import BrandingFields from '../Configure/Partials/BrandingFields.vue';
import AdvertisementFields from '../Configure/Partials/AdvertisementFields.vue';
import { inputClass, keysFor, uploadFields } from '../Configure/Partials/fields';
import { usePreviewImages } from '../Configure/Partials/useSettingsPreview';
import LinkedDisplays from './Partials/LinkedDisplays.vue';

const props = defineProps({
    shell: Object, profile: Object, displays: Array, sections: Object, values: Object, images: Object, options: Object, urls: Object,
});

const fields = { behavior: BehaviorFields, display: DisplayFields, texts: TextsFields, branding: BrandingFields, advertisement: AdvertisementFields };

// One form for the whole profile: the name, every section's keys and the uploads.
const initial = () => ({
    name: props.profile?.name ?? '',
    ...Object.fromEntries(Object.keys(props.sections).flatMap((s) => keysFor(s, props.options)).map((k) => [k, props.values[k]])),
    ...uploadFields.branding,
    ...(props.sections.advertisement ? uploadFields.advertisement : {}),
});
const form = useForm(initial());

function fieldProps(key) {
    if (key === 'texts') return { form, fallback: 'the built-in text' };
    if (key === 'branding') return { form, images: props.images, options: props.options };
    if (key === 'advertisement') return { form, images: props.images, durationMax: 120 };
    return { form };
}

function save() {
    form.transform((data) => (props.profile ? { ...data, _method: 'put' } : data))
        .post(props.urls.save, { forceFormData: true, preserveScroll: true });
}

const confirmDelete = ref(false);
const deleting = ref(false);
function destroy() {
    deleting.value = true;
    router.delete(props.profile.deleteUrl, { onFinish: () => { deleting.value = false; } });
}

const settings = computed(() => ({ ...props.values, ...form.data() }));
const preview = usePreviewImages(form, form, () => props.images, () => props.options);
const exampleRoom = computed(() => props.displays[0]?.roomName || 'Meeting room');

const count = computed(() => props.displays.length);
const chip = computed(() => (count.value ? `Followed by ${count.value} ${count.value === 1 ? 'display' : 'displays'}` : 'Not used by a display yet'));
const sectionIds = computed(() => Object.keys(props.sections));
const nav = computed(() => [
    { id: 'general', label: 'General' },
    ...Object.entries(props.sections).map(([key, s]) => ({ id: key, label: s.label })),
]);
const hasErrors = computed(() => Object.keys(form.errors).length > 0);
</script>

<template>
    <Head :title="profile ? `Edit ${profile.name}` : 'New profile'" />

    <PortalShell :shell="shell">
        <header class="flex flex-wrap items-center gap-x-4 gap-y-2 border-b border-line bg-surface px-6 py-3 md:px-8">
            <Link :href="urls.back" class="inline-flex items-center gap-2 text-[13px] font-semibold text-ink-3 hover:text-navy">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6" /></svg>Profiles
            </Link>
            <span class="text-offline-border" aria-hidden="true">/</span>
            <h1 class="text-base font-semibold">{{ profile ? profile.name : 'New profile' }}</h1>
            <Chip v-if="profile" tone="navy">{{ chip }}</Chip>
            <span class="flex-1"></span>
            <span v-if="form.isDirty" class="text-xs font-medium text-amber-text">Unsaved changes</span>
            <Button v-if="profile?.canDelete" variant="ghost" size="sm" @click="confirmDelete = true">Delete</Button>
            <Button size="sm" :disabled="form.processing" @click="save">
                {{ profile ? (count ? `Save for ${count} ${count === 1 ? 'display' : 'displays'}` : 'Save profile') : 'Create profile' }}
            </Button>
        </header>

        <div class="flex flex-col gap-6 px-6 pt-7 pb-10 md:px-8">
            <Notice v-if="hasErrors" tone="danger" title="Some settings need another look">Check the fields marked below.</Notice>

            <div class="flex flex-wrap items-start gap-7">
                <SectionNav :items="nav" />

                <form class="flex max-w-[600px] min-w-0 flex-[1_1_380px] flex-col gap-5" @submit.prevent="save">
                    <SectionCard id="general" title="General" description="A profile keeps the settings of many displays in step.">
                        <FieldRow label="Profile name" for="name" help="For your own reference, never shown on a display." :error="form.errors.name">
                            <input id="name" v-model="form.name" type="text" maxlength="255" required placeholder="Meeting rooms, ground floor" :class="inputClass">
                        </FieldRow>
                        <p class="text-xs text-muted">
                            A display follows its profile per section. When a display gets its own values in one section, the others keep following this profile.
                        </p>
                    </SectionCard>

                    <SectionCard v-for="key in sectionIds" :id="key" :key="key" :title="sections[key].label" :description="sections[key].description">
                        <component :is="fields[key]" v-bind="fieldProps(key)" />
                    </SectionCard>

                    <div class="flex flex-wrap items-center justify-end gap-3">
                        <Button variant="secondary" :href="urls.back">Cancel</Button>
                        <Button type="submit" :disabled="form.processing">{{ profile ? 'Save profile' : 'Create profile' }}</Button>
                    </div>
                </form>

                <aside aria-label="Live preview" class="sticky top-6 flex max-w-[640px] min-w-0 flex-[1_1_420px] flex-col gap-3.5">
                    <TabletPreview :settings="settings" :room="exampleRoom"
                                   :logo="preview.logo.value" :background="preview.background.value" :advertisement="preview.advertisement.value" />
                    <LinkedDisplays v-if="profile" :displays="displays" :displays-url="urls.displays" />
                </aside>
            </div>
        </div>

        <Modal v-if="profile" :open="confirmDelete" title="Delete this profile?" @close="confirmDelete = false">
            <p class="text-ink-2">
                {{ profile.name }} is removed. Displays that follow it keep its settings as their own, so nothing changes on the wall.
            </p>
            <template #footer>
                <Button variant="secondary" @click="confirmDelete = false">Cancel</Button>
                <Button variant="danger" :disabled="deleting" @click="destroy">Delete profile</Button>
            </template>
        </Modal>
    </PortalShell>
</template>
