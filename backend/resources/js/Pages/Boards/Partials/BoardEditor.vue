<script setup>
// The editor beside the board list: the main card from the design (name, layout, theme, rooms,
// public link), then the board's other options in the same card style. One form, one save.
import { computed, onBeforeUnmount, ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import Icon from '../../../Components/Icon.vue';
import Button from '../../../DesignSystem/Button.vue';
import SettingRow from '../../../DesignSystem/SettingRow.vue';
import Toggle from '../../../DesignSystem/Toggle.vue';
import Segmented from './Segmented.vue';
import RoomPicker from './RoomPicker.vue';
import CategoryEditor from './CategoryEditor.vue';

const props = defineProps({
    board: { type: Object, default: null }, // null: a new board
    workspaceId: { type: String, required: true },
    displays: { type: Array, required: true },
    fonts: { type: Array, required: true },
    languages: { type: Array, required: true },
    urls: { type: Object, required: true },
});

const b = props.board;
const form = useForm({
    workspace_id: props.workspaceId,
    name: b?.name ?? '',
    title: b?.title ?? '',
    subtitle: b?.subtitle ?? '',
    view_mode: b?.view_mode ?? 'card',
    theme: b?.theme ?? 'dark',
    show_all_displays: b?.show_all_displays ?? true,
    display_ids: [...(b?.display_ids ?? [])],
    categories: (b?.categories ?? []).map((c) => ({ name: c.name, display_ids: [...c.display_ids] })),
    show_title: b?.show_title ?? true,
    show_booker: b?.show_booker ?? true,
    show_next_event: b?.show_next_event ?? true,
    show_meeting_title: b?.show_meeting_title ?? true,
    show_join_button: b?.show_join_button ?? false,
    show_transitioning: b?.show_transitioning ?? true,
    transitioning_minutes: b?.transitioning_minutes ?? 10,
    font_family: b?.font_family ?? 'Inter',
    language: b?.language ?? 'en',
    is_public: b?.is_public ?? false,
    logo: null,
    remove_logo: false,
});

const layouts = [{ value: 'card', label: 'Cards' }, { value: 'table', label: 'Table' }, { value: 'grid', label: 'Grid' }];
const themes = [{ value: 'dark', label: 'Dark' }, { value: 'light', label: 'Light' }, { value: 'system', label: 'System' }];
const field = 'w-full rounded-lg border border-line bg-surface px-3 py-[9px] text-sm text-ink focus:border-navy focus:outline-none';

const onBoard = computed(() => (form.show_all_displays ? null : form.display_ids));
const missingRooms = computed(() => !form.show_all_displays && props.displays.length > 0 && !form.display_ids.some((id) => props.displays.some((d) => d.id === id)));

// Errors without a field of their own in the editor still need to be seen.
const shown = ['name', 'title', 'subtitle', 'display_ids', 'logo', 'transitioning_minutes'];
const otherErrors = computed(() => Object.entries(form.errors).filter(([k]) => !shown.includes(k)).map(([, v]) => v));

// Logo: a picked file previews straight away; removing only happens on save.
const logoInput = ref(null);
const logoPreview = ref(null);
function pickLogo(e) {
    const file = e.target.files?.[0] ?? null;
    form.logo = file;
    form.remove_logo = false;
    if (logoPreview.value) URL.revokeObjectURL(logoPreview.value);
    logoPreview.value = file ? URL.createObjectURL(file) : null;
}
function clearLogo() {
    if (form.logo) {
        form.logo = null;
        if (logoPreview.value) URL.revokeObjectURL(logoPreview.value);
        logoPreview.value = null;
        if (logoInput.value) logoInput.value.value = '';
    } else {
        form.remove_logo = true;
    }
}
onBeforeUnmount(() => { if (logoPreview.value) URL.revokeObjectURL(logoPreview.value); });
const currentLogo = computed(() => logoPreview.value ?? (form.remove_logo ? null : b?.logoUrl));

const copied = ref(false);
async function copyLink() {
    await navigator.clipboard?.writeText(b.publicUrl);
    copied.value = true;
    setTimeout(() => { copied.value = false; }, 2000);
}

function save() {
    form.transform((d) => ({
        ...d,
        display_ids: d.show_all_displays ? [] : d.display_ids,
        ...(b ? { _method: 'put' } : {}),
    })).post(b ? b.updateUrl : props.urls.store, { forceFormData: true, preserveScroll: true });
}

function destroy() {
    if (!window.confirm(`Delete "${b.name}"? Screens showing this board will stop updating.`)) return;
    router.delete(b.destroyUrl);
}
</script>

<template>
    <form class="flex flex-col gap-5" novalidate @submit.prevent="save">
        <div class="sp-card flex flex-col gap-[18px] px-[22px] py-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="font-display text-base font-bold tracking-tight">{{ b ? b.name : 'New board' }}</h2>
                <a v-if="b" :href="b.showUrl" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 text-xs font-semibold text-navy hover:underline">
                    <Icon name="external" :size="14" />Open board
                </a>
            </div>

            <div class="flex flex-wrap items-end gap-3.5">
                <label class="flex flex-[1_1_240px] flex-col gap-1.5">
                    <span class="text-[13px] font-medium">Board name</span>
                    <input v-model="form.name" type="text" required maxlength="255" placeholder="For example Reception overview" :class="field">
                    <span v-if="form.errors.name" class="text-xs text-reserved-text">{{ form.errors.name }}</span>
                </label>
                <div class="flex flex-col gap-1.5">
                    <span class="text-[13px] font-medium">Layout</span>
                    <Segmented v-model="form.view_mode" label="Layout" :options="layouts" />
                </div>
                <div class="flex flex-col gap-1.5">
                    <span class="text-[13px] font-medium">Theme</span>
                    <Segmented v-model="form.theme" label="Theme" :options="themes" />
                </div>
            </div>
            <p class="-mt-3 text-xs text-muted">Only you see the name. The board shows its title, below.</p>

            <RoomPicker v-model:show-all="form.show_all_displays" :displays="displays" :selected="form.display_ids" :error="form.errors.display_ids" />

            <div class="flex flex-wrap items-center gap-3.5 border-t border-line-soft pt-4">
                <span class="flex flex-[1_1_220px] flex-col">
                    <span class="text-[13px] font-medium">Public link</span>
                    <span class="text-xs text-muted">Anyone with the link can view the board, no login needed.</span>
                </span>
                <template v-if="form.is_public && b?.publicUrl">
                    <code class="max-w-full truncate rounded-lg bg-surface-raised px-3 py-2 font-mono text-xs text-navy">{{ b.publicUrl }}</code>
                    <Button variant="secondary" size="sm" @click="copyLink">{{ copied ? 'Copied' : 'Copy' }}</Button>
                    <a :href="b.publicUrl" target="_blank" rel="noopener" class="sp-btn sp-btn-secondary sp-btn-sm">Open</a>
                </template>
                <span v-else-if="form.is_public" class="text-xs text-muted">Save to create the link.</span>
                <span v-else-if="b?.publicUrl" class="text-xs text-checkin-text">Saving turns the link off. A new link is made if you turn it on again.</span>
                <Toggle v-model="form.is_public" label="Public link" />
            </div>
        </div>

        <div class="sp-card flex flex-col gap-4 px-[22px] py-5">
            <h3 class="text-[15px] font-semibold">Header</h3>
            <div class="flex flex-wrap gap-3.5">
                <label class="flex flex-[1_1_240px] flex-col gap-1.5">
                    <span class="text-[13px] font-medium">Title</span>
                    <input v-model="form.title" type="text" maxlength="255" placeholder="Meeting room overview" :class="field">
                    <span class="text-xs text-muted">Shown large at the top. Left empty, it reads "Meeting room overview" in the board's language.</span>
                    <span v-if="form.errors.title" class="text-xs text-reserved-text">{{ form.errors.title }}</span>
                </label>
                <label class="flex flex-[1_1_240px] flex-col gap-1.5">
                    <span class="text-[13px] font-medium">Subtitle</span>
                    <input v-model="form.subtitle" type="text" maxlength="255" placeholder="For example Floor 2" :class="field">
                    <span class="text-xs text-muted">Optional, smaller, below the title.</span>
                    <span v-if="form.errors.subtitle" class="text-xs text-reserved-text">{{ form.errors.subtitle }}</span>
                </label>
            </div>
            <div class="flex flex-wrap items-center gap-3.5">
                <span class="flex h-14 w-36 flex-none items-center justify-center overflow-hidden rounded-lg border border-line bg-surface-raised">
                    <img v-if="currentLogo" :src="currentLogo" alt="Board logo" class="max-h-12 max-w-32 object-contain">
                    <span v-else class="text-xs text-muted">No logo</span>
                </span>
                <span class="flex flex-[1_1_220px] flex-col">
                    <span class="text-[13px] font-medium">Logo</span>
                    <span class="text-xs text-muted">Top left of the board. JPG, PNG or GIF up to 2 MB, about 200 × 60 px works best.</span>
                    <span v-if="form.errors.logo" class="text-xs text-reserved-text">{{ form.errors.logo }}</span>
                </span>
                <input ref="logoInput" type="file" accept="image/png,image/jpeg,image/gif" class="sr-only" id="board-logo" @change="pickLogo">
                <label for="board-logo" class="sp-btn sp-btn-secondary sp-btn-sm cursor-pointer"><Icon name="upload" :size="14" />{{ currentLogo ? 'Replace' : 'Upload' }}</label>
                <Button v-if="currentLogo" variant="ghost" size="sm" @click="clearLogo">Remove</Button>
            </div>
        </div>

        <div class="sp-card flex flex-col gap-1 px-[22px] py-5">
            <h3 class="text-[15px] font-semibold">What the board shows</h3>
            <SettingRow label="Meeting name" help="The name of the meeting on each room.">
                <Toggle v-model="form.show_title" label="Meeting name" />
            </SettingRow>
            <SettingRow label="Real meeting titles" help="Off shows the room's reserved text instead, for privacy-sensitive spaces.">
                <Toggle v-model="form.show_meeting_title" label="Real meeting titles" />
            </SettingRow>
            <SettingRow label="Organizer" help="Who booked the meeting.">
                <Toggle v-model="form.show_booker" label="Organizer" />
            </SettingRow>
            <SettingRow label="Next meeting" help="What comes next in a room that is free now.">
                <Toggle v-model="form.show_next_event" label="Next meeting" />
            </SettingRow>
            <SettingRow label="Join button" help="On meetings with a Teams, Zoom or Google Meet link.">
                <Toggle v-model="form.show_join_button" label="Join button" />
            </SettingRow>
            <SettingRow label="Starting or ending soon" help="Shows a room in amber when a meeting starts or ends within the minutes you set.">
                <input v-if="form.show_transitioning" v-model.number="form.transitioning_minutes" type="number" min="1" max="60" aria-label="Minutes"
                       class="w-16 rounded-lg border border-line bg-surface px-2 py-1.5 text-sm focus:border-navy focus:outline-none">
                <span v-if="form.show_transitioning" class="text-xs text-muted">min</span>
                <Toggle v-model="form.show_transitioning" label="Starting or ending soon" />
            </SettingRow>
            <span v-if="form.errors.transitioning_minutes" class="text-xs text-reserved-text">{{ form.errors.transitioning_minutes }}</span>
            <div class="mt-3 flex flex-wrap gap-3.5 border-t border-line-soft pt-4">
                <label class="flex flex-[1_1_200px] flex-col gap-1.5">
                    <span class="text-[13px] font-medium">Font</span>
                    <select v-model="form.font_family" :class="field">
                        <option v-for="f in fonts" :key="f" :value="f">{{ f }}</option>
                    </select>
                </label>
                <label class="flex flex-[1_1_200px] flex-col gap-1.5">
                    <span class="text-[13px] font-medium">Language</span>
                    <select v-model="form.language" :class="field">
                        <option v-for="l in languages" :key="l.value" :value="l.value">{{ l.label }}</option>
                    </select>
                </label>
            </div>
        </div>

        <div class="sp-card flex flex-col gap-3 px-[22px] py-5">
            <div class="flex flex-col gap-0.5">
                <h3 class="text-[15px] font-semibold">Room categories</h3>
                <span class="text-xs text-muted">Group the rooms into sections, for example per floor. The first category is shown at the top; rooms you leave out come last, under "Other".</span>
            </div>
            <CategoryEditor :categories="form.categories" :displays="displays" :on-board="onBoard" />
        </div>

        <div v-if="otherErrors.length" class="flex flex-col gap-1 rounded-lg border border-reserved-border bg-reserved-bg px-4 py-3 text-[13px] text-reserved-text" role="alert">
            <span v-for="(e, i) in otherErrors" :key="i">{{ e }}</span>
        </div>

        <div class="sticky bottom-0 z-10 -mx-1 flex flex-wrap items-center gap-3 border-t border-line bg-ground/95 px-1 py-3 backdrop-blur">
            <Button v-if="b?.canDelete" variant="danger" @click="destroy"><template #icon><Icon name="trash" :size="16" /></template>Delete board</Button>
            <span v-if="form.isDirty" class="text-xs text-muted">Unsaved changes</span>
            <div class="ml-auto flex items-center gap-3">
                <Link v-if="!b" :href="urls.index" class="sp-btn sp-btn-ghost sp-btn-md">Cancel</Link>
                <Button type="submit" :disabled="form.processing || missingRooms">{{ b ? 'Save changes' : 'Create board' }}</Button>
            </div>
        </div>
    </form>
</template>
