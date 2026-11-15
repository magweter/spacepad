<script setup>
// One alert set by an admin, drawn as a "Needs attention" row: the tag at a fixed width, so the
// text of every row starts on the same line. Used in the portal shell and the admin preview.
defineProps({
    tone: { type: String, default: 'attention' }, // attention | action_required
    title: { type: String, default: '' },
    body: { type: String, default: null },
});

const tones = {
    attention: 'border-checkin bg-checkin-bg text-checkin-text',
    action_required: 'border-reserved-border bg-reserved-bg text-reserved-text',
};
const tags = { attention: 'Attention', action_required: 'Action required' };
</script>

<template>
    <div class="flex flex-wrap items-center gap-4 rounded-xl border px-4 py-3 text-[13px]" :class="tones[tone] ?? tones.attention"
         :role="tone === 'action_required' ? 'alert' : 'status'">
        <span class="flex w-[76px] flex-none items-center justify-center rounded-lg border-[1.5px] border-current bg-surface px-2 py-0.5 text-center text-[11px] leading-tight font-semibold">{{ tags[tone] ?? tags.attention }}</span>
        <span class="flex min-w-0 flex-1 flex-col">
            <span class="font-semibold">{{ title }}</span>
            <span v-if="body" class="text-xs opacity-85">{{ body }}</span>
        </span>
    </div>
</template>
