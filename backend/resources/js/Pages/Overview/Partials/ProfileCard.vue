<script setup>
// Two optional questions after the first display. Each click is saved straight away; the card
// goes once the last question is answered or someone closes it.
import { ref } from 'vue';
import ChoicePill from '../../../Components/ChoicePill.vue';
import Icon from '../../../Components/Icon.vue';
import Button from '../../../DesignSystem/Button.vue';

const props = defineProps({ card: Object });

const rooms = ref(props.card.rooms);
const solution = ref(null);
const other = ref('');
const otherError = ref('');
const done = ref(false);
const hidden = ref(false);

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';
const post = (url, body) => fetch(url, {
    method: 'POST',
    headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf() },
    body: body ? JSON.stringify(body) : undefined,
}).catch(() => {});

function pickRooms(value) {
    rooms.value = value;
    post(props.card.urls.update, { question: 'rooms', value });
}

function pickSolution(value) {
    solution.value = value;
    if (value === 'other') return;
    post(props.card.urls.update, { question: 'previous_solution', value });
    finish();
}

function saveOther() {
    if (!other.value.trim()) { otherError.value = 'Enter a name first'; return; }
    post(props.card.urls.update, { question: 'previous_solution', value: 'other', text: other.value.trim() });
    finish();
}

function finish() {
    done.value = true;
    setTimeout(() => { hidden.value = true; }, 2500);
}

function dismiss() {
    hidden.value = true;
    post(props.card.urls.dismiss);
}
</script>

<template>
    <div v-if="!hidden" class="sp-card flex flex-col gap-3 px-5 py-[18px]">
        <template v-if="done">
            <h2 class="font-display text-base font-bold tracking-tight">Thanks, that helps.</h2>
            <p class="text-[13px] text-muted">This card won't show again.</p>
        </template>
        <template v-else>
            <div class="flex items-start justify-between gap-3">
                <h2 class="font-display text-base font-bold tracking-tight">{{ card.firstDisplay ? 'Your first display is live. Two quick questions?' : 'Two quick questions?' }}</h2>
                <button type="button" aria-label="Dismiss" class="text-muted hover:text-ink" @click="dismiss"><Icon name="close" :size="16" /></button>
            </div>
            <p class="text-[13px] text-muted">It helps us set you up the right way. One click each, skip whenever you like.</p>
            <span class="text-[13px] font-semibold">How many rooms do you want to show?</span>
            <div class="flex flex-wrap gap-2">
                <ChoicePill v-for="o in card.roomOptions" :key="o.value" :selected="rooms === o.value" @select="pickRooms(o.value)">{{ o.label }}</ChoicePill>
            </div>
            <div class="flex flex-col gap-2" :class="rooms ? '' : 'pointer-events-none opacity-50'">
                <span class="text-[13px] font-semibold">What did you use before Spacepad?</span>
                <div class="flex flex-wrap gap-2">
                    <ChoicePill v-for="o in card.solutionOptions" :key="o.value" :selected="solution === o.value" @select="pickSolution(o.value)">{{ o.label }}</ChoicePill>
                </div>
                <div v-if="solution === 'other'" class="flex gap-2">
                    <input v-model="other" type="text" maxlength="255" placeholder="Name of the product" @input="otherError = ''" @keydown.enter.prevent="saveOther"
                           class="min-w-0 flex-1 rounded-[10px] border border-offline-border bg-surface px-3 py-2 text-sm focus:border-navy focus:outline-none">
                    <Button variant="secondary" @click="saveOther">Save</Button>
                </div>
                <span v-if="otherError" class="text-xs text-reserved-text">{{ otherError }}</span>
            </div>
        </template>
    </div>
</template>
