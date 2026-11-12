<script setup>
// The room calendars the displays read, with the source each comes from and who uses it.
import StatusDot from '../../../DesignSystem/StatusDot.vue';

defineProps({ calendars: { type: Array, required: true }, newDisplayUrl: { type: String, required: true } });

const columns = 'grid-cols-[2fr_1.2fr_1.6fr_110px]';
</script>

<template>
    <section aria-label="Room calendars" class="min-w-0 overflow-x-auto rounded-xl border border-line bg-surface">
        <div class="flex flex-wrap items-center gap-3 border-b border-line px-5 py-4">
            <h2 class="text-[15px] font-semibold">Room calendars</h2>
            <span class="text-xs text-muted">{{ calendars.length }} {{ calendars.length === 1 ? 'calendar' : 'calendars' }} from your sources</span>
        </div>

        <p v-if="!calendars.length" class="px-5 py-6 text-[13px] text-muted">
            No room calendar in use yet. <a :href="newDisplayUrl" class="font-semibold text-navy hover:underline">Add a display</a> and pick its room.
        </p>

        <div v-else class="min-w-[600px]">
            <div class="grid gap-3 border-b border-line px-5 py-2.5 text-[11px] font-semibold tracking-[0.04em] text-muted uppercase" :class="columns">
                <span>Room calendar</span><span>Source</span><span>Used by</span><span>Status</span>
            </div>
            <div v-for="c in calendars" :key="c.id" class="grid items-center gap-3 border-b border-line-soft px-5 py-3 text-[13px] last:border-b-0"
                 :class="[columns, c.displays.length ? '' : 'bg-surface-raised']">
                <span class="flex min-w-0 flex-col">
                    <span class="truncate font-semibold">{{ c.name }}</span>
                    <span v-if="c.email" class="truncate text-xs text-muted">{{ c.email }}</span>
                </span>
                <span>{{ c.source }}</span>
                <span v-if="c.displays.length" class="min-w-0">
                    <template v-for="(d, i) in c.displays" :key="d.url"><a :href="d.url" class="font-medium text-navy hover:underline">{{ d.name }}</a><span v-if="i < c.displays.length - 1">, </span></template>
                </span>
                <span v-else class="text-muted">Not used yet</span>
                <span v-if="c.displays.length" class="inline-flex items-center gap-1.5 text-xs font-semibold text-available-text"><StatusDot status="available" />Mapped</span>
                <a v-else :href="newDisplayUrl" class="text-xs font-semibold text-navy hover:underline">Add display →</a>
            </div>
        </div>
    </section>
</template>
