<script setup>
// Calendar sync diagnostics for one display (Pro): traces the event pipeline step by step through
// displays.diagnostics.run, and can reset an errored calendar account and run again.
import { computed, ref, watch } from 'vue';
import Modal from '../../../DesignSystem/Modal.vue';
import Button from '../../../DesignSystem/Button.vue';
import Notice from '../../../DesignSystem/Notice.vue';
import StatusDot from '../../../DesignSystem/StatusDot.vue';

const props = defineProps({ display: Object });
const emit = defineEmits(['close']);

const running = ref(false);
const steps = ref([]);
const ran = ref(false);
const elapsed = ref(null);
const failure = ref(null);
const resetting = ref(false);
const resetMessage = ref(null);
const expanded = ref({});

function clear() {
    steps.value = [];
    ran.value = false;
    elapsed.value = null;
    failure.value = null;
    resetMessage.value = null;
    expanded.value = {};
}
watch(() => props.display?.id, clear);

// True when a step reports the linked account in the error state: then resetting it helps.
const accountErrored = computed(() => steps.value.some((s) => s.data && s.data['Account status'] === 'error'));
const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

async function run() {
    if (!props.display || running.value) return;
    clear();
    running.value = true;
    const t0 = Date.now();
    try {
        const resp = await fetch(props.display.urls.diagnostics, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
        if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
        const json = await resp.json();
        steps.value = json.steps ?? [];
        elapsed.value = ((Date.now() - t0) / 1000).toFixed(1);
        ran.value = true;
        // Open the first step that is not fine.
        const bad = steps.value.find((s) => s.status !== 'ok');
        if (bad) expanded.value = { [bad.number]: true };
    } catch (err) {
        failure.value = err.message;
    } finally {
        running.value = false;
    }
}

async function resetAccount() {
    if (!props.display || resetting.value) return;
    resetting.value = true;
    resetMessage.value = null;
    let message;
    try {
        const resp = await fetch(props.display.urls.resetAccount, {
            method: 'POST',
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf() },
        });
        const json = await resp.json().catch(() => ({}));
        if (!resp.ok || json.ok === false) throw new Error(json.message || `HTTP ${resp.status}`);
        message = json.message ?? 'Account status reset.';
        await run();
    } catch (err) {
        message = `Reset failed: ${err.message}`;
    } finally {
        resetting.value = false;
        resetMessage.value = message;
    }
}

const tone = { ok: 'available', warning: 'checkin', error: 'reserved' };
const word = { ok: 'OK', warning: 'Warning', error: 'Error' };
const border = { ok: 'border-available-border', warning: 'border-checkin-border', error: 'border-reserved-border' };
const fields = (step) => Object.entries(step.data ?? {}).filter(([k]) => k !== 'events');
</script>

<template>
    <Modal :open="!!display" title="Calendar sync diagnostics" width="680px" @close="emit('close')">
        <template v-if="display">
            <p class="-mt-2 text-[13px] text-muted">Trace the event pipeline of {{ display.name }} and find where events go missing.</p>

            <div class="flex flex-wrap items-center gap-3">
                <Button :disabled="running" @click="run">{{ running ? 'Running' : ran ? 'Run again' : 'Run diagnostic' }}</Button>
                <span v-if="ran" class="text-xs text-muted">Done in {{ elapsed }} s</span>
            </div>

            <Notice v-if="failure" tone="danger" :title="`Request failed: ${failure}`" />

            <p v-if="!ran && !running && !failure" class="text-[13px] text-muted">We call the live calendar connection and check that it is configured correctly.</p>

            <div v-if="accountErrored" class="flex flex-col gap-2 rounded-[10px] border border-reserved-border bg-reserved-bg px-4 py-3 text-[13px] text-reserved-text">
                <span class="font-semibold">The calendar account is in an error state</span>
                <span class="text-xs">Automatic token refresh is skipped while it is. Reset it to connected to try a fresh token. If that fails again, the account keeps its error state and the reason is written to the logs.</span>
                <span class="flex flex-wrap items-center gap-3">
                    <Button size="sm" variant="secondary" :disabled="resetting" @click="resetAccount">{{ resetting ? 'Resetting' : 'Reset account status and retry' }}</Button>
                </span>
            </div>
            <p v-if="resetMessage" class="text-xs text-ink-3" role="status">{{ resetMessage }}</p>

            <ol v-if="steps.length" class="flex max-h-[55vh] flex-col gap-2 overflow-y-auto">
                <li v-for="step in steps" :key="step.number" class="overflow-hidden rounded-[10px] border" :class="border[step.status] ?? 'border-line'">
                    <button type="button" class="flex w-full items-center gap-3 px-4 py-3 text-left hover:bg-surface-raised" :aria-expanded="!!expanded[step.number]"
                            @click="expanded = { ...expanded, [step.number]: !expanded[step.number] }">
                        <span class="inline-flex h-6 w-6 flex-none items-center justify-center rounded-full border border-line text-xs font-bold text-ink-3">{{ step.number }}</span>
                        <span class="flex min-w-0 flex-1 flex-col">
                            <span class="font-semibold">{{ step.title }}</span>
                            <span class="text-xs text-ink-3">{{ step.message }}</span>
                        </span>
                        <span :class="['sp-badge', `sp-badge-${tone[step.status] ?? 'offline'}`]"><StatusDot :status="tone[step.status] ?? 'offline'" />{{ word[step.status] ?? step.status }}</span>
                    </button>
                    <div v-if="expanded[step.number]" class="flex flex-col gap-3 border-t border-line-soft px-4 py-3 text-xs">
                        <dl v-if="fields(step).length" class="grid grid-cols-[auto_1fr] gap-x-6 gap-y-1">
                            <template v-for="[key, val] in fields(step)" :key="key">
                                <dt class="font-medium whitespace-nowrap text-muted">{{ key }}</dt>
                                <dd class="break-all">{{ val ?? '-' }}</dd>
                            </template>
                        </dl>
                        <div v-if="step.data?.events?.length" class="flex flex-col gap-1.5">
                            <span class="font-semibold tracking-[0.04em] text-muted uppercase">Events ({{ step.data.events.length }} shown)</span>
                            <div v-for="(ev, i) in step.data.events" :key="i" class="flex items-start gap-2 rounded-md border border-line bg-surface-raised px-3 py-2">
                                <span class="flex min-w-0 flex-1 flex-col">
                                    <span class="truncate font-semibold">{{ ev.title ?? ev.summary ?? 'No title' }}</span>
                                    <span class="text-muted">{{ ev.start ?? '' }} to {{ ev.end ?? '' }}</span>
                                </span>
                                <span v-if="ev.all_day === 'Yes'" class="sp-chip sp-chip-amber">All day</span>
                                <span v-if="ev.free === 'Yes'" class="sp-chip sp-chip-neutral">Free</span>
                            </div>
                        </div>
                        <p v-else-if="step.data?.events" class="text-muted italic">No events to show.</p>
                        <p v-if="!fields(step).length && !step.data?.events" class="text-muted italic">No additional details.</p>
                    </div>
                </li>
            </ol>
        </template>
    </Modal>
</template>
