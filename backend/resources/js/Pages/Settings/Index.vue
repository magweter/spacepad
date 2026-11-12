<script setup>
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import PortalShell from '../../Components/PortalShell.vue';
import FlashMessage from '../../Components/FlashMessage.vue';
import Button from '../../DesignSystem/Button.vue';
import SettingRow from '../../DesignSystem/SettingRow.vue';
import DangerZone from './Partials/DangerZone.vue';

const props = defineProps({ shell: Object, workspace: Object, can: Object, deleteBlocked: String, leave: Object, urls: Object });

const rename = useForm({ name: props.workspace.name });
const saveName = () => rename.patch(props.urls.update, { preserveScroll: true, preserveState: true, onSuccess: () => rename.defaults() });

const creating = ref(false);
const create = useForm({ name: '' });
const createWorkspace = () => create.post(props.urls.store, { preserveState: true });

const field = 'rounded-lg border border-line bg-surface px-3 py-2 text-[13px] focus:border-navy focus:outline-none';
</script>

<template>
    <Head title="Settings" />

    <PortalShell :shell="shell" title="Settings">
        <div class="flex max-w-[808px] flex-col gap-4 px-6 pt-7 pb-10 md:px-8">
            <p class="-mt-2 text-[13px] text-muted">Workspace-wide. Per-display behaviour lives in <a :href="urls.profiles" class="font-semibold text-navy hover:underline">Profiles</a>.</p>

            <FlashMessage />

            <section id="workspace" class="sp-card gap-0 px-[18px] py-1.5">
                <div class="flex flex-col gap-0.5 border-b border-line pt-3 pb-1.5">
                    <h2 class="text-[15px] font-semibold">Workspace</h2>
                    <span class="text-xs text-muted">Everyone in this workspace manages the same displays, boards and calendar accounts.</span>
                </div>

                <SettingRow label="Workspace name" help="Shown in the workspace switcher and to everyone you invite">
                    <form v-if="can.rename" class="flex flex-wrap items-center gap-2" @submit.prevent="saveName">
                        <label for="workspace_name" class="sr-only">Workspace name</label>
                        <input id="workspace_name" v-model="rename.name" type="text" required maxlength="255" :class="[field, 'w-[260px] max-w-full']">
                        <Button type="submit" variant="secondary" :disabled="rename.processing || !rename.isDirty">Save</Button>
                    </form>
                    <span v-else class="text-[13px] font-medium">{{ workspace.name }}</span>
                </SettingRow>
                <p v-if="rename.errors.name" class="-mt-2 pb-2 text-right text-xs text-reserved-text">{{ rename.errors.name }}</p>

                <SettingRow label="Members" :help="`${workspace.members} ${workspace.members === 1 ? 'person' : 'people'} in this workspace`">
                    <a :href="urls.team" class="text-[13px] font-semibold text-navy hover:underline">Manage team</a>
                </SettingRow>

                <SettingRow label="Another workspace" help="For a second company or location. You become its owner and switch to it">
                    <form v-if="creating" class="flex flex-wrap items-center gap-2" @submit.prevent="createWorkspace">
                        <label for="new_workspace" class="sr-only">Name of the new workspace</label>
                        <input id="new_workspace" v-model="create.name" type="text" required maxlength="255" placeholder="Workspace name" :class="[field, 'w-[220px] max-w-full']">
                        <Button type="submit" :disabled="create.processing">Create</Button>
                        <Button variant="ghost" @click="creating = false">Cancel</Button>
                    </form>
                    <Button v-else variant="secondary" @click="creating = true">Create workspace</Button>
                </SettingRow>
                <p v-if="create.errors.name" class="-mt-2 pb-2 text-right text-xs text-reserved-text">{{ create.errors.name }}</p>
            </section>

            <DangerZone :workspace="workspace" :can="can" :delete-blocked="deleteBlocked" :leave="leave" :urls="urls" />
        </div>
    </PortalShell>
</template>
