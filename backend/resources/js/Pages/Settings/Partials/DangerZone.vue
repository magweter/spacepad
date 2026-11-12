<script setup>
// Leaving and deleting. Leaving as the last owner hands the workspace to someone first; deleting
// is only for an empty workspace and asks for its name.
import { ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import Button from '../../../DesignSystem/Button.vue';
import SettingRow from '../../../DesignSystem/SettingRow.vue';
import Modal from '../../../DesignSystem/Modal.vue';

const props = defineProps({ workspace: Object, can: Object, deleteBlocked: String, leave: Object, urls: Object });

const leaving = ref(false);
const leaveForm = useForm({ successor_user_id: props.leave.candidates[0]?.id ?? '' });
function leaveWorkspace() {
    leaveForm.transform((data) => (props.leave.needsSuccessor ? data : {})).post(props.urls.leave, { preserveState: true });
}

const deleteForm = useForm({ confirm_name: '' });
const deleting = ref(false);
const deleteWorkspace = () => deleteForm.delete(props.urls.destroy, { preserveScroll: true, preserveState: true });

const field = 'rounded-lg border border-line bg-surface px-3 py-2 text-[13px] focus:border-navy focus:outline-none';
</script>

<template>
    <section v-if="can.leave || can.delete || deleteBlocked" id="danger" class="sp-card gap-0 border-reserved-border px-[18px] py-1.5">
        <div class="flex flex-col gap-0.5 border-b border-reserved-border pt-3 pb-1.5">
            <h2 class="text-[15px] font-semibold text-reserved-text">Danger zone</h2>
            <span class="text-xs text-muted">These cannot be undone from the portal.</span>
        </div>

        <SettingRow v-if="can.leave" label="Leave workspace" help="You lose access to its displays, boards and calendar accounts. Nothing is deleted">
            <Button variant="danger" @click="leaving = true">Leave workspace</Button>
        </SettingRow>

        <SettingRow v-if="can.delete" label="Delete workspace" help="This workspace is empty and you are its only member">
            <Button variant="danger" @click="deleting = true">Delete workspace</Button>
        </SettingRow>
        <SettingRow v-else-if="deleteBlocked" label="Delete workspace" :help="deleteBlocked">
            <Button variant="danger" disabled>Delete workspace</Button>
        </SettingRow>
    </section>

    <Modal :open="leaving" :title="`Leave ${workspace.name}?`" @close="leaving = false">
        <form id="leave-workspace" class="flex flex-col gap-3" @submit.prevent="leaveWorkspace">
            <p class="text-ink-2">You lose access to its displays, boards and calendar accounts. Nothing is deleted.</p>
            <label v-if="leave.needsSuccessor" class="flex flex-col gap-1">
                <span class="text-[13px] font-semibold">You are the only owner. Choose who takes over</span>
                <select v-model="leaveForm.successor_user_id" required :class="field">
                    <option v-for="c in leave.candidates" :key="c.id" :value="c.id">{{ c.name }} ({{ c.email }})</option>
                </select>
            </label>
            <span v-if="leaveForm.errors.successor_user_id" class="text-xs text-reserved-text">{{ leaveForm.errors.successor_user_id }}</span>
        </form>
        <template #footer>
            <Button variant="secondary" @click="leaving = false">Stay</Button>
            <button type="submit" form="leave-workspace" class="sp-btn sp-btn-md border-reserved bg-reserved text-white hover:opacity-90 disabled:opacity-50" :disabled="leaveForm.processing">Leave workspace</button>
        </template>
    </Modal>

    <Modal :open="deleting" title="Delete this workspace?" @close="deleting = false">
        <form id="delete-workspace" class="flex flex-col gap-3" @submit.prevent="deleteWorkspace">
            <p class="text-ink-2">Useful when you moved everything into another workspace and no longer need this one. This cannot be undone.</p>
            <label class="flex flex-col gap-1">
                <span class="text-[13px] font-semibold">Type <span class="font-mono">{{ workspace.name }}</span> to confirm</span>
                <input v-model="deleteForm.confirm_name" type="text" autocomplete="off" required :class="field">
            </label>
            <span v-if="deleteForm.errors.confirm_name" class="text-xs text-reserved-text">{{ deleteForm.errors.confirm_name }}</span>
        </form>
        <template #footer>
            <Button variant="secondary" @click="deleting = false">Keep workspace</Button>
            <button type="submit" form="delete-workspace" class="sp-btn sp-btn-md border-reserved bg-reserved text-white hover:opacity-90 disabled:opacity-50"
                    :disabled="deleteForm.processing || deleteForm.confirm_name !== workspace.name">Permanently delete</button>
        </template>
    </Modal>
</template>
