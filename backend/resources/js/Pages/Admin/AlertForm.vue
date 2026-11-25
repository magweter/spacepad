<script setup>
// Create or edit an alert, with a preview of the row the portal will show.
import { Head, Link, useForm } from '@inertiajs/vue3';
import PortalShell from '../../Components/PortalShell.vue';
import FlashMessage from '../../Components/FlashMessage.vue';
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
});

// Empty fields go out as null: no provider means every workspace, no start means now.
const payload = (data) => ({
    ...Object.fromEntries(Object.entries(data).map(([k, v]) => [k, v === '' ? null : v])),
    starts_at: toIso(data.starts_at),
    ends_at: toIso(data.ends_at),
});
const submit = () => form.transform(payload)[props.alert ? 'put' : 'post'](props.urls.submit);

const title = props.alert ? 'Edit alert' : 'New alert';
const labelClass = 'mb-1 block text-[13px] font-semibold';
const fieldClass = 'block w-full rounded-lg border border-line bg-surface px-3 py-2 text-[13px] text-ink focus:border-navy focus:outline-none';
</script>

<template>
    <Head :title="title" />

    <PortalShell :shell="shell" :title="title">
        <template #actions>
            <Link :href="urls.index" class="inline-flex items-center gap-1.5 text-[13px] font-semibold text-navy hover:underline">
                <Icon name="arrowLeft" :size="15" />Back to Alerts
            </Link>
        </template>

        <div class="flex max-w-2xl flex-col gap-6 px-6 pt-7 pb-10 md:px-8">
            <FlashMessage />

            <section aria-label="Preview" class="flex flex-col gap-2">
                <span class="text-xs font-semibold tracking-wide text-muted uppercase">Preview</span>
                <AlertRow :tone="form.tone" :title="form.title || 'Your title'" :body="form.body || null" />
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
