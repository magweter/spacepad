<script setup>
import { computed, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import PortalShell from '../../Components/PortalShell.vue';
import { openUpgrade } from '../../Components/Upgrade/upgrade';
import Icon from '../../Components/Icon.vue';
import Button from '../../DesignSystem/Button.vue';
import EmptyState from '../../DesignSystem/EmptyState.vue';
import Modal from '../../DesignSystem/Modal.vue';
import BoardCards from './Partials/BoardCards.vue';
import BoardEditor from './Partials/BoardEditor.vue';
import BoardPreview from './Partials/BoardPreview.vue';

const props = defineProps({
    shell: Object, hasPro: Boolean, canCreate: Boolean, boards: Array, creating: Boolean, board: Object,
    workspaceId: String, displays: Array, fonts: Array, languages: Array, showPublic: Boolean, urls: Object,
});

const editorKey = computed(() => (props.creating ? 'new' : `${props.board?.id}-${props.board?.version}`));
const showEditor = computed(() => props.creating || props.board);

// The public link pops up once, right after it was switched on.
const publicOpen = ref(props.showPublic);
const copied = ref(false);
async function copy() {
    await navigator.clipboard?.writeText(props.board.publicUrl);
    copied.value = true;
    setTimeout(() => { copied.value = false; }, 2000);
}
</script>

<template>
    <Head :title="creating ? 'New board' : board ? `${board.name}, boards` : 'Boards'" />

    <PortalShell :shell="shell" title="Boards" subtitle="Overviews of many rooms at once, for TVs, lobbies and the browser.">
        <template #actions>
            <Button v-if="canCreate && !creating" :href="urls.create"><template #icon><Icon name="plus" :size="16" :stroke="2.5" /></template>New board</Button>
        </template>

        <div class="flex flex-col gap-6 px-6 pt-6 pb-10 md:px-10">


            <!-- Free workspace: what boards are and how to get them. -->
            <div v-if="!hasPro" class="sp-card flex max-w-2xl flex-col gap-4 px-6 py-6">
                <div class="flex flex-col gap-1.5">
                    <h2 class="font-display text-xl font-bold tracking-tight">Every room on one screen</h2>
                    <p class="text-ink-3">A board shows which rooms are free and which are in use, on a TV in the lobby, a screen in the hallway or a browser tab. Pick the rooms, the layout and the theme, and share it with a public link if you like.</p>
                </div>
                <ul class="flex flex-col gap-1.5 text-[13px] text-ink-2">
                    <li class="flex items-center gap-2"><Icon name="check" :size="14" :stroke="2.5" class="text-available" />Cards, table or grid layout, dark or light</li>
                    <li class="flex items-center gap-2"><Icon name="check" :size="14" :stroke="2.5" class="text-available" />Rooms grouped per floor or wing</li>
                    <li class="flex items-center gap-2"><Icon name="check" :size="14" :stroke="2.5" class="text-available" />A public link for screens without a login</li>
                </ul>
                <p class="text-xs text-muted">Boards are part of Pro. A board is billed as two displays.</p>
                <div><Button variant="accent" @click="openUpgrade('boards')">See what Pro adds</Button></div>
            </div>

            <div v-else-if="!boards.length && !creating" class="sp-card">
                <EmptyState title="No boards yet">
                    Create a board to show room availability on a lobby or hallway screen.
                    <template v-if="canCreate" #actions>
                        <Button :href="urls.create"><template #icon><Icon name="plus" :size="16" :stroke="2.5" /></template>Create a board</Button>
                    </template>
                </EmptyState>
            </div>

            <!-- One board open: all its settings and the large preview. -->
            <section v-else-if="showEditor" aria-label="Board editor" class="flex min-w-0 flex-col gap-5">
                <a :href="urls.index" class="inline-flex items-center gap-1.5 self-start text-[13px] font-semibold text-muted hover:text-navy">
                    <Icon name="arrowLeft" :size="14" />All boards
                </a>
                <BoardEditor :key="editorKey" :board="creating ? null : board" :workspace-id="workspaceId" :displays="displays"
                             :fonts="fonts" :languages="languages" :urls="urls" />
                <BoardPreview v-if="!creating && board" :src="board.showUrl" :version="board.version" />
            </section>

            <!-- The overview: the boards as cards with a preview. -->
            <BoardCards v-else :boards="boards" :can-create="canCreate" :create-url="urls.create" />
        </div>

        <Modal :open="publicOpen && !!board?.publicUrl" title="Your board has a public link" @close="publicOpen = false">
            <p class="text-ink-3">Anyone with this link can view {{ board?.name }} without logging in. Open it on the screen that shows the board.</p>
            <div class="flex flex-wrap items-center gap-2">
                <code class="min-w-0 flex-1 truncate rounded-lg bg-surface-raised px-3 py-2 font-mono text-xs text-navy">{{ board?.publicUrl }}</code>
                <Button variant="secondary" size="sm" @click="copy">{{ copied ? 'Copied' : 'Copy' }}</Button>
            </div>
            <template #footer>
                <Button variant="secondary" :href="board?.publicUrl" external>Open board</Button>
                <Button @click="publicOpen = false">Done</Button>
            </template>
        </Modal>
    </PortalShell>
</template>
