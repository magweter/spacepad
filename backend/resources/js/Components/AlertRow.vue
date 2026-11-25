<script setup>
// One alert set by an admin, drawn as a "Needs attention" row: the tag at a fixed width, so the
// text of every row starts on the same line. Used in the portal shell, the overview and the admin preview.
import { computed } from 'vue';
import { toneOf } from './alertTones.js';

const props = defineProps({
    tone: { type: String, default: 'attention' }, // a key of alertTones
    title: { type: String, default: '' },
    body: { type: String, default: null },
});

const t = computed(() => toneOf(props.tone));
</script>

<template>
    <div class="flex flex-wrap items-center gap-4 rounded-xl border px-4 py-3 text-[13px]" :class="t.row"
         :role="t.attention && tone !== 'attention' ? 'alert' : 'status'">
        <span class="flex w-[96px] flex-none items-center justify-center rounded-lg border-[1.5px] border-current bg-surface px-2 py-0.5 text-center text-[11px] leading-tight font-semibold">{{ t.label }}</span>
        <span class="flex min-w-0 flex-1 flex-col">
            <span class="font-semibold">{{ title }}</span>
            <span v-if="body" class="text-xs opacity-85">{{ body }}</span>
        </span>
    </div>
</template>
