<script setup>
// One settings section: title and description, an optional state on the right, the fields, and
// an optional footer for its save.
defineProps({
    id: { type: String, required: true },
    title: { type: String, required: true },
    description: { type: String, default: null },
});
</script>

<template>
    <section :id="id" class="sp-card flex scroll-mt-6 flex-col gap-4 px-[22px] py-5" :aria-labelledby="`${id}-title`">
        <div class="flex flex-wrap items-start gap-3">
            <div class="flex min-w-0 flex-[1_1_220px] flex-col gap-0.5">
                <h2 :id="`${id}-title`" class="text-[15px] font-semibold">{{ title }}</h2>
                <p v-if="description" class="text-[13px] text-muted">{{ description }}</p>
            </div>
            <div v-if="$slots.state" class="flex flex-none flex-wrap items-center gap-2"><slot name="state" /></div>
        </div>
        <slot />
        <div v-if="$slots.footer" class="flex flex-wrap items-center justify-end gap-3 border-t border-line-soft pt-4"><slot name="footer" /></div>
    </section>
</template>
