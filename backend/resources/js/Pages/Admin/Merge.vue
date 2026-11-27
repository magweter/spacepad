<script setup>
// Admin: merge two workspaces that should have been one. Preview first (no side effects), then
// type the source name to merge. Irreversible.
import { computed, ref, watch } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import PortalShell from '../../Components/PortalShell.vue';
import Button from '../../DesignSystem/Button.vue';
import Card from '../../DesignSystem/Card.vue';
import AdminTabs from './Partials/AdminTabs.vue';
import MergePreview from './Partials/MergePreview.vue';
import WorkspacePicker from './Partials/WorkspacePicker.vue';

const props = defineProps({
    shell: Object, stats: Object, pendingRoadmap: Number,
    source: Object, target: Object, options: Object, preview: Object, urls: Object,
});

const optionList = [
    { key: 'move_members', label: 'Move members across (a source owner becomes an admin; existing roles are never downgraded)' },
    { key: 'rename_collisions', label: 'Rename clashing display, board and profile names to "name (source workspace)"' },
    { key: 'delete_source', label: 'Delete the source workspace afterwards, if it ends up empty and unbilled' },
    { key: 'adopt_orphans', label: 'Adopt rows with no workspace at all (legacy data invisible to every workspace query)' },
];

const form = useForm({
    source_workspace_id: props.source?.id ?? '',
    target_workspace_id: props.target?.id ?? '',
    ...Object.fromEntries(optionList.map((o) => [o.key, !!props.options?.[o.key]])),
});

// The preview is a POST; the address bar stays on /admin/merge so a failed merge comes back here
// (and a reload works). The last preview is kept on screen until a merge succeeds.
const shown = ref(null);
watch(() => props.preview, (preview) => {
    if (preview) shown.value = { ...preview, source: props.source, target: props.target, options: { ...props.options } };
}, { immediate: true });

function submitPreview() {
    form.transform((data) => Object.fromEntries(Object.entries(data).filter(([, v]) => v !== false).map(([k, v]) => [k, v === true ? '1' : v])))
        .post(props.urls.preview, { preserveUrl: true, preserveState: true, preserveScroll: true });
}

// What the pickers start with: the workspaces of the last preview, or of ?source= and ?target= links.
const sourceInitial = computed(() => props.source ?? null);
const targetInitial = computed(() => props.target ?? null);
</script>

<template>
    <Head title="Merge workspaces" />

    <PortalShell :shell="shell" title="Admin">
        <div class="flex flex-col gap-6 px-6 pt-6 pb-10 md:px-10">
            <AdminTabs active="merge" :stats="stats" :pending-roadmap="pendingRoadmap" />

            <div class="flex max-w-[1040px] flex-col gap-4">
                <Card>
                    <h2 class="font-display text-base font-bold tracking-tight">Merge two workspaces</h2>
                    <p class="mt-1 text-[13px] text-muted">
                        Moves everything from the source workspace into the target. For customers who each signed up separately
                        before invitations existed. This cannot be undone, so preview first.
                    </p>

                    <form class="mt-5 flex flex-col gap-5" @submit.prevent="submitPreview">
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <WorkspacePicker id="source_workspace_id" v-model="form.source_workspace_id" label="Source workspace (emptied)" :lookup-url="urls.lookup"
                                             :initial="sourceInitial" :exclude="form.target_workspace_id" :error="form.errors.source_workspace_id" />
                            <WorkspacePicker id="target_workspace_id" v-model="form.target_workspace_id" label="Target workspace (kept)" :lookup-url="urls.lookup"
                                             :initial="targetInitial" :exclude="form.source_workspace_id" :error="form.errors.target_workspace_id" />
                        </div>

                        <fieldset class="flex flex-col gap-2.5">
                            <legend class="mb-2 text-[13px] font-medium text-ink">Options</legend>
                            <label v-for="o in optionList" :key="o.key" class="flex items-start gap-2.5 text-[13px] text-ink-2">
                                <input v-model="form[o.key]" type="checkbox" :name="o.key" value="1" class="mt-0.5 h-4 w-4 flex-none accent-navy">
                                {{ o.label }}
                            </label>
                        </fieldset>

                        <div><Button type="submit" :disabled="form.processing">Preview merge</Button></div>
                    </form>
                </Card>

                <MergePreview v-if="shown" :preview="shown" :url="urls.store" @merged="shown = null" />
            </div>
        </div>
    </PortalShell>
</template>
