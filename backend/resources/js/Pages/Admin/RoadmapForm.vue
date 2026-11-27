<script setup>
// Create or edit a roadmap item. Items created here are approved straight away.
import { Head, Link, useForm } from '@inertiajs/vue3';
import PortalShell from '../../Components/PortalShell.vue';
import Icon from '../../Components/Icon.vue';
import Button from '../../DesignSystem/Button.vue';
import Card from '../../DesignSystem/Card.vue';

const props = defineProps({
    shell: Object, stats: Object, pendingRoadmap: Number,
    item: { type: Object, default: null }, statuses: Array, defaultStatus: String, urls: Object,
});

const form = useForm({
    title: props.item?.title ?? '',
    description: props.item?.description ?? '',
    status: props.item?.status ?? props.defaultStatus,
    expected_at: props.item?.expected_at ?? '',
    sort_order: props.item?.sort_order ?? 0,
});

const submit = () => (props.item ? form.put(props.urls.submit) : form.post(props.urls.submit));

const title = props.item ? 'Edit roadmap item' : 'New roadmap item';
const labelClass = 'mb-1 block text-[13px] font-semibold';
const fieldClass = 'block w-full rounded-md border border-line bg-surface px-3 py-2 text-[13px] text-ink focus:border-navy focus:outline-none';
</script>

<template>
    <Head :title="title" />

    <PortalShell :shell="shell" :title="title">
        <template #actions>
            <Link :href="urls.index" class="inline-flex items-center gap-1.5 text-[13px] font-semibold text-navy hover:underline">
                <Icon name="arrowLeft" :size="15" />Back to Roadmap
            </Link>
        </template>

        <div class="flex max-w-2xl flex-col gap-6 px-6 pt-6 pb-10 md:px-10">

            <form class="flex flex-col gap-5" @submit.prevent="submit">
                <Card>
                    <div class="flex flex-col gap-5">
                        <div>
                            <label for="title" :class="labelClass">Title <span class="text-reserved-text" aria-hidden="true">*</span></label>
                            <input id="title" v-model="form.title" type="text" name="title" required maxlength="150" placeholder="e.g. CalDAV two-way sync" :class="fieldClass">
                            <span v-if="form.errors.title" class="mt-1 block text-xs text-reserved-text">{{ form.errors.title }}</span>
                        </div>

                        <div>
                            <label for="description" :class="labelClass">Description</label>
                            <textarea id="description" v-model="form.description" name="description" rows="3" maxlength="2000"
                                      placeholder="Optional, more detail shown in the panel" :class="[fieldClass, 'resize-none']"></textarea>
                            <span v-if="form.errors.description" class="mt-1 block text-xs text-reserved-text">{{ form.errors.description }}</span>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label for="status" :class="labelClass">Status <span class="text-reserved-text" aria-hidden="true">*</span></label>
                                <select id="status" v-model="form.status" name="status" :class="fieldClass">
                                    <option v-for="s in statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
                                </select>
                                <span v-if="form.errors.status" class="mt-1 block text-xs text-reserved-text">{{ form.errors.status }}</span>
                            </div>
                            <div>
                                <label for="expected_at" :class="labelClass">Expected date</label>
                                <input id="expected_at" v-model="form.expected_at" type="date" name="expected_at" :class="fieldClass">
                                <span v-if="form.errors.expected_at" class="mt-1 block text-xs text-reserved-text">{{ form.errors.expected_at }}</span>
                            </div>
                        </div>

                        <div class="w-32">
                            <label for="sort_order" :class="labelClass">Sort order</label>
                            <input id="sort_order" v-model="form.sort_order" type="number" name="sort_order" min="0" :class="fieldClass">
                            <p class="mt-1 text-xs text-muted">Lower = higher in list</p>
                            <span v-if="form.errors.sort_order" class="mt-1 block text-xs text-reserved-text">{{ form.errors.sort_order }}</span>
                        </div>
                    </div>
                </Card>

                <div class="flex items-center gap-3">
                    <Button type="submit" :disabled="form.processing">{{ item ? 'Save changes' : 'Create item' }}</Button>
                    <Link :href="urls.index" class="text-[13px] text-muted hover:text-ink">Cancel</Link>
                </div>
            </form>
        </div>
    </PortalShell>
</template>
