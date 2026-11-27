<script setup>
// Create or edit an alert, with a preview of the row the portal will show.
import { computed, onBeforeUnmount, ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import PortalShell from '../../Components/PortalShell.vue';
import Icon from '../../Components/Icon.vue';
import AlertRow from '../../Components/AlertRow.vue';
import { alertTones } from '../../Components/alertTones.js';
import Button from '../../DesignSystem/Button.vue';
import Card from '../../DesignSystem/Card.vue';

const props = defineProps({ shell: Object, stats: Object, pendingRoadmap: Number, alert: { type: Object, default: null }, urls: Object });

// The server sends and expects ISO 8601 times; the inputs show them in the admin's own time zone.
const pad = (n) => String(n).padStart(2, '0');
const toLocalInput = (iso) => {
    if (!iso) return '';
    const d = new Date(iso);
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
};
const toIso = (local) => (local ? new Date(local).toISOString() : null);

const form = useForm({
    title: props.alert?.title ?? '',
    body: props.alert?.body ?? '',
    tone: props.alert?.tone ?? 'attention',
    provider: props.alert?.provider ?? '',
    popup: props.alert?.popup ?? false,
    starts_at: toLocalInput(props.alert?.starts_at),
    ends_at: toLocalInput(props.alert?.ends_at),
    image: null,
    remove_image: false,
});

// The image on top of the popup: a new file previews at once; the saved one can be removed.
const localPreview = ref(null);
function pickImage(e) {
    const file = e.target.files?.[0] ?? null;
    form.image = file;
    form.remove_image = false;
    if (localPreview.value) URL.revokeObjectURL(localPreview.value);
    localPreview.value = file ? URL.createObjectURL(file) : null;
}
function removeImage() {
    form.image = null;
    form.remove_image = true;
    if (localPreview.value) URL.revokeObjectURL(localPreview.value);
    localPreview.value = null;
}
const imagePreview = computed(() => localPreview.value ?? (form.remove_image ? null : props.alert?.image ?? null));
onBeforeUnmount(() => { if (localPreview.value) URL.revokeObjectURL(localPreview.value); });

// Empty fields go out as null: no provider means every workspace, no start means now.
const payload = (data) => ({
    ...Object.fromEntries(Object.entries(data).map(([k, v]) => [k, v === '' ? null : v])),
    starts_at: toIso(data.starts_at),
    ends_at: toIso(data.ends_at),
});
// Sent as multipart, so the image comes along; an edit says PUT through _method.
const submit = () => form
    .transform((data) => ({ ...payload(data), remove_image: data.remove_image ? 1 : 0, ...(props.alert ? { _method: 'put' } : {}) }))
    .post(props.urls.submit, { forceFormData: true });

const title = props.alert ? 'Edit alert' : 'New alert';
const labelClass = 'mb-1 block text-[13px] font-semibold';
const fieldClass = 'block w-full rounded-md border border-line bg-surface px-3 py-2 text-[13px] text-ink focus:border-navy focus:outline-none';
</script>

<template>
    <Head :title="title" />

    <PortalShell :shell="shell" :title="title">
        <template #actions>
            <Link :href="urls.index" class="inline-flex items-center gap-1.5 text-[13px] font-semibold text-navy hover:underline">
                <Icon name="arrowLeft" :size="15" />Back to Alerts
            </Link>
        </template>

        <div class="flex max-w-2xl flex-col gap-6 px-6 pt-6 pb-10 md:px-10">

            <section aria-label="Preview" class="flex flex-col gap-2">
                <span class="text-xs font-semibold tracking-wide text-muted uppercase">Preview</span>
                <AlertRow :tone="form.tone" :title="form.title || 'Your title'" :body="form.body || null" standalone />
            </section>

            <form class="flex flex-col gap-5" @submit.prevent="submit">
                <Card>
                    <div class="flex flex-col gap-5">
                        <div>
                            <label for="title" :class="labelClass">Title <span class="text-reserved-text" aria-hidden="true">*</span></label>
                            <input id="title" v-model="form.title" type="text" required maxlength="120" placeholder="e.g. Microsoft 365 has an outage" :class="fieldClass">
                            <span v-if="form.errors.title" class="mt-1 block text-xs text-reserved-text">{{ form.errors.title }}</span>
                        </div>

                        <div>
                            <label for="body" :class="labelClass">Text</label>
                            <textarea id="body" v-model="form.body" rows="3" maxlength="500"
                                      placeholder="e.g. Meetings may load slowly or not at all. Nothing is wrong with your setup." :class="[fieldClass, 'resize-none']"></textarea>
                            <span v-if="form.errors.body" class="mt-1 block text-xs text-reserved-text">{{ form.errors.body }}</span>
                        </div>

                        <div>
                            <label for="image" :class="labelClass">Image</label>
                            <div v-if="imagePreview" class="mb-2 flex items-start gap-3">
                                <img :src="imagePreview" alt="Image preview" class="max-h-36 rounded-md border border-line-soft object-cover">
                                <button type="button" class="sp-btn sp-btn-secondary sp-btn-sm" @click="removeImage">Remove image</button>
                            </div>
                            <input id="image" type="file" accept="image/png,image/jpeg,image/webp,image/gif" class="block text-[13px] text-ink-2 file:mr-3 file:rounded-md file:border file:border-line file:bg-surface file:px-3 file:py-1.5 file:text-[13px] file:font-semibold file:text-navy" @change="pickImage">
                            <p class="mt-1 text-xs text-muted">Optional. Shown on top of the popup, for example a screenshot of something new. JPG, PNG, WebP or GIF, up to 4 MB.</p>
                            <span v-if="form.errors.image" class="mt-1 block text-xs text-reserved-text">{{ form.errors.image }}</span>
                        </div>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label for="tone" :class="labelClass">Type</label>
                                <select id="tone" v-model="form.tone" :class="fieldClass">
                                    <option v-for="(t, key) in alertTones" :key="key" :value="key">{{ t.label }}</option>
                                </select>
                                <span v-if="form.errors.tone" class="mt-1 block text-xs text-reserved-text">{{ form.errors.tone }}</span>
                            </div>
                            <div>
                                <label for="provider" :class="labelClass">Show to</label>
                                <select id="provider" v-model="form.provider" :class="fieldClass">
                                    <option value="">All workspaces</option>
                                    <option value="microsoft">Workspaces with Microsoft 365</option>
                                    <option value="google">Workspaces with Google</option>
                                    <option value="caldav">Workspaces with CalDAV</option>
                                </select>
                                <span v-if="form.errors.provider" class="mt-1 block text-xs text-reserved-text">{{ form.errors.provider }}</span>
                            </div>
                            <div>
                                <label for="starts_at" :class="labelClass">Starts</label>
                                <input id="starts_at" v-model="form.starts_at" type="datetime-local" :class="fieldClass">
                                <p class="mt-1 text-xs text-muted">Empty: straight away</p>
                                <span v-if="form.errors.starts_at" class="mt-1 block text-xs text-reserved-text">{{ form.errors.starts_at }}</span>
                            </div>
                            <div>
                                <label for="ends_at" :class="labelClass">Ends</label>
                                <input id="ends_at" v-model="form.ends_at" type="datetime-local" :class="fieldClass">
                                <p class="mt-1 text-xs text-muted">Empty: until you end it</p>
                                <span v-if="form.errors.ends_at" class="mt-1 block text-xs text-reserved-text">{{ form.errors.ends_at }}</span>
                            </div>
                        </div>

                        <label class="flex items-start gap-3 text-[13px]">
                            <input v-model="form.popup" type="checkbox" class="mt-0.5 size-4 rounded border border-line accent-navy">
                            <span>
                                <span class="block font-semibold">Show as a popup</span>
                                <span class="block text-xs text-muted">Every user sees it once as a popup in the portal, then as a row like the preview.</span>
                            </span>
                        </label>
                    </div>
                </Card>

                <div class="flex items-center gap-3">
                    <Button type="submit" :disabled="form.processing">{{ alert ? 'Save changes' : 'Create alert' }}</Button>
                    <Link :href="urls.index" class="text-[13px] text-muted hover:text-ink">Cancel</Link>
                </div>
            </form>
        </div>
    </PortalShell>
</template>
