<script setup>
// Configure one display: its names, then each settings section, saved on its own. A section with
// a linked profile either follows that profile or holds its own values; saving a section is what
// gives it its own values, and "Follow profile" drops them again. The tablet preview follows the
// form as you edit.
import { computed, reactive, ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import PortalShell from '../../Components/PortalShell.vue';
import FlashMessage from '../../Components/FlashMessage.vue';
import Button from '../../DesignSystem/Button.vue';
import Chip from '../../DesignSystem/Chip.vue';
import Modal from '../../DesignSystem/Modal.vue';
import Notice from '../../DesignSystem/Notice.vue';
import StatusBadge from '../../DesignSystem/StatusBadge.vue';
import SectionCard from './Partials/SectionCard.vue';
import SectionNav from './Partials/SectionNav.vue';
import FieldRow from './Partials/FieldRow.vue';
import TabletPreview from './Partials/TabletPreview.vue';
import BehaviorFields from './Partials/BehaviorFields.vue';
import DisplayFields from './Partials/DisplayFields.vue';
import TextsFields from './Partials/TextsFields.vue';
import BrandingFields from './Partials/BrandingFields.vue';
import AdvertisementFields from './Partials/AdvertisementFields.vue';
import { inputClass, keysFor, numberClass, uploadFields } from './Partials/fields';
import { usePreviewImages } from './Partials/useSettingsPreview';

const props = defineProps({
    shell: Object, display: Object, profile: Object, sections: Object, values: Object, images: Object, options: Object, urls: Object,
});

const fields = { behavior: BehaviorFields, display: DisplayFields, texts: TextsFields, branding: BrandingFields, advertisement: AdvertisementFields };

// Each section's fields get only the props they declare.
function fieldProps(key) {
    const base = { form: forms[key] };
    if (key === 'texts') return { ...base, fallback: props.profile ? "the profile's text, or the built-in one" : 'the built-in text' };
    if (key === 'branding') return { ...base, images: props.images, options: props.options, inheritedFrom: props.profile?.name };
    if (key === 'advertisement') return { ...base, images: props.images, inheritedFrom: props.profile?.name };
    return base;
}

// One form per section, holding only that section's keys (plus its uploads).
const initial = (section) => ({
    ...Object.fromEntries(keysFor(section, props.options).map((k) => [k, props.values[k]])),
    ...(uploadFields[section] || {}),
});
const forms = reactive(Object.fromEntries(Object.keys(props.sections).map((key) => [key, useForm(initial(key))])));

// After a save or reset the page comes back with fresh values: start the section over from them.
function refresh(keys) {
    keys.forEach((key) => { forms[key].defaults(initial(key)); forms[key].reset(); });
}

function save(key) {
    forms[key]
        .transform((data) => ({ ...data, _method: 'put' }))
        .post(props.sections[key].update, { forceFormData: true, preserveScroll: true, onSuccess: () => refresh([key]) });
}

const resetting = ref(null);
function followProfile(key) {
    resetting.value = key;
    router.post(props.sections[key].reset, {}, { preserveScroll: true, onSuccess: () => refresh([key]), onFinish: () => { resetting.value = null; } });
}

const confirmReset = ref(false);
const resettingAll = ref(false);
function resetAll() {
    resettingAll.value = true;
    router.post(props.urls.resetToProfile, {}, {
        preserveScroll: true,
        onSuccess: () => { confirmReset.value = false; refresh(Object.keys(forms)); },
        onFinish: () => { resettingAll.value = false; },
    });
}

// Location and seats start from the calendar provider's floor and capacity for the room when the
// display has none yet; nothing is stored until Save, so the form counts as changed from the start.
const suggestion = props.display.roomSuggestion || {};
const suggestedLocation = props.display.location == null && suggestion.location ? suggestion.location : null;
const suggestedCapacity = props.display.capacity == null && suggestion.capacity ? suggestion.capacity : null;
const names = useForm({
    name: props.display.name,
    display_name: props.display.roomName || '',
    location: props.display.location ?? '',
    capacity: props.display.capacity ?? '',
});
if (suggestedLocation) names.location = suggestedLocation;
if (suggestedCapacity) names.capacity = suggestedCapacity;
const saveNames = () => names.put(props.urls.rename, { preserveScroll: true, onSuccess: () => names.defaults() });

// The line under the room name on the tablet, as the app joins it.
const subline = computed(() => {
    const seats = Number(names.capacity) > 0 ? `${names.capacity} ${Number(names.capacity) === 1 ? 'seat' : 'seats'}` : null;
    return [String(names.location || '').trim(), seats].filter(Boolean).join(', ');
});

// Everything the preview needs, straight from the forms.
const settings = computed(() => Object.assign({}, props.values, ...Object.values(forms).map((f) => f.data())));
const preview = usePreviewImages(forms.branding, forms.advertisement, () => props.images, () => props.options);

const ownSections = computed(() => Object.values(props.sections).filter((s) => !s.follows).length);
const chip = computed(() => {
    if (!props.profile) return 'No profile';
    if (!ownSections.value) return `Profile: ${props.profile.name}`;
    return `Profile: ${props.profile.name}, ${ownSections.value} ${ownSections.value === 1 ? 'section' : 'sections'} its own`;
});

const nav = computed(() => [
    { id: 'general', label: 'General', note: names.isDirty ? 'Unsaved' : null },
    ...Object.entries(props.sections).map(([key, s]) => ({ id: key, label: s.label, note: forms[key].isDirty ? 'Unsaved' : null })),
]);

const status = computed(() => {
    if (!props.display.device) return { tone: 'offline', word: 'No tablet' };
    return props.display.device.online ? { tone: 'available', word: 'Online' } : { tone: 'offline', word: 'Offline' };
});
const errorBag = computed(() => Object.values(forms).map((f) => f.errors.error).find(Boolean));
</script>

<template>
    <Head :title="`Configure ${display.name}`" />

    <PortalShell :shell="shell">
        <header class="flex flex-wrap items-center gap-x-4 gap-y-2 border-b border-line bg-surface px-6 py-3 md:px-8">
            <Link :href="urls.back" class="inline-flex items-center gap-2 text-[13px] font-semibold text-ink-3 hover:text-navy">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6" /></svg>Displays
            </Link>
            <span class="text-offline-border" aria-hidden="true">/</span>
            <h1 class="text-base font-semibold">{{ display.name }}</h1>
            <Chip :tone="profile ? 'navy' : 'neutral'">{{ chip }}</Chip>
            <span class="flex-1"></span>
            <Button v-if="profile?.deviates" variant="secondary" size="sm" @click="confirmReset = true">Reset to profile</Button>
        </header>

        <div class="flex flex-col gap-6 px-6 pt-7 pb-10 md:px-8">
            <FlashMessage />
            <Notice v-if="errorBag" tone="danger" :title="errorBag" />

            <div class="flex flex-wrap items-start gap-7">
                <SectionNav :items="nav" />

                <div class="flex max-w-[600px] min-w-0 flex-[1_1_380px] flex-col gap-5">
                    <SectionCard id="general" title="General" description="The names of this display and where the room is. Calendar and status are shown for reference.">
                        <div class="flex flex-col gap-3">
                            <FieldRow label="Display name" for="name" help="Only used in the portal, to tell displays apart." :error="names.errors.name">
                                <input id="name" v-model="names.name" type="text" maxlength="255" required :class="inputClass">
                            </FieldRow>
                            <FieldRow label="Room name" for="display_name" help="Shown in the top right corner of the tablet." :error="names.errors.display_name">
                                <input id="display_name" v-model="names.display_name" type="text" maxlength="255" required :class="inputClass">
                            </FieldRow>
                            <FieldRow label="Location" for="location" :error="names.errors.location"
                                      :help="suggestedLocation ? 'Filled in from your calendar. Save to keep it.' : 'Optional. Shown under the room name, for example Floor 1.'">
                                <input id="location" v-model="names.location" type="text" maxlength="64" placeholder="Floor 1" :class="inputClass">
                            </FieldRow>
                            <FieldRow label="Seats" for="capacity" :error="names.errors.capacity"
                                      :help="suggestedCapacity ? 'Filled in from your calendar. Save to keep it.' : 'Optional. Shown under the room name as 12 seats.'">
                                <input id="capacity" v-model="names.capacity" type="number" min="1" max="999" placeholder="12" :class="numberClass">
                            </FieldRow>
                        </div>
                        <template #footer>
                            <Button size="sm" :disabled="names.processing || !names.isDirty" @click="saveNames">Save</Button>
                        </template>
                    </SectionCard>

                    <Notice v-if="profile" tone="info" :title="`This display follows ${profile.name}`">
                        Each section below follows the profile until you save it here; then that section has its own values and the others keep following.
                        <Link :href="profile.editUrl" class="font-semibold underline">Edit the profile</Link>
                    </Notice>
                    <Notice v-else tone="neutral" title="This display is not linked to a profile">
                        Everything below applies to this display only. A profile keeps many displays in step;
                        <Link :href="urls.back" class="font-semibold underline">link one from the Displays page</Link>.
                    </Notice>

                    <SectionCard v-for="(section, key) in sections" :id="key" :key="key" :title="section.label" :description="section.description">
                        <template v-if="profile" #state>
                            <Chip v-if="section.follows" tone="available">Follows profile</Chip>
                            <template v-else>
                                <Chip tone="amber">Own settings</Chip>
                                <Button variant="ghost" size="sm" :disabled="resetting === key" @click="followProfile(key)">Follow profile</Button>
                            </template>
                        </template>

                        <component :is="fields[key]" v-bind="fieldProps(key)" />

                        <template #footer>
                            <span v-if="profile && section.follows" class="mr-auto text-xs text-muted">Saving gives this section its own values; the rest keeps following {{ profile.name }}.</span>
                            <span v-if="forms[key].recentlySuccessful" class="text-xs font-medium text-available-text">Saved</span>
                            <Button v-if="forms[key].isDirty" variant="ghost" size="sm" @click="forms[key].reset()">Discard</Button>
                            <Button size="sm" :disabled="forms[key].processing || !forms[key].isDirty" @click="save(key)">Save {{ section.label.toLowerCase() }}</Button>
                        </template>
                    </SectionCard>
                </div>

                <aside aria-label="Live preview" class="sticky top-6 flex max-w-[640px] min-w-0 flex-[1_1_420px] flex-col gap-3.5">
                    <TabletPreview :settings="settings" :room="names.display_name || display.name" :subline="subline"
                                   :logo="preview.logo.value" :background="preview.background.value" :advertisement="preview.advertisement.value" />

                    <div class="sp-card flex flex-col gap-2 px-[18px] py-3.5 text-[13px]">
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-muted">Tablet</span>
                            <span class="flex items-center gap-2 font-medium">
                                <span v-if="display.device" class="truncate">{{ display.device.name }}</span>
                                <StatusBadge :status="status.tone">{{ status.word }}</StatusBadge>
                            </span>
                        </div>
                        <div v-if="display.device?.lastSeen" class="flex justify-between gap-3"><span class="text-muted">Last seen</span><span class="font-medium">{{ display.device.lastSeen }}</span></div>
                        <div class="flex justify-between gap-3"><span class="text-muted">Calendar</span><span class="truncate font-medium">{{ display.calendar || '-' }}</span></div>
                        <div class="flex justify-between gap-3"><span class="text-muted">Status</span><span class="font-medium">{{ display.statusLabel || '-' }}</span></div>
                        <div class="flex justify-between gap-3"><span class="text-muted">Last sync</span><span class="font-medium">{{ display.lastSync || 'Never' }}</span></div>
                        <p class="border-t border-line-soft pt-2 text-xs text-muted">Saved changes reach the tablet within a minute.</p>
                    </div>
                </aside>
            </div>
        </div>

        <Modal :open="confirmReset" title="Reset every section to the profile?" @close="confirmReset = false">
            <p class="text-ink-2">All settings this display holds on its own are removed, and it follows {{ profile?.name }} again in every section.</p>
            <template #footer>
                <Button variant="secondary" @click="confirmReset = false">Cancel</Button>
                <Button variant="danger" :disabled="resettingAll" @click="resetAll">Reset to profile</Button>
            </template>
        </Modal>
    </PortalShell>
</template>
