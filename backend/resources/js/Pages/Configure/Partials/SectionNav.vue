<script setup>
// The section list beside the settings; marks the section in view.
import { onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps({ items: { type: Array, required: true } }); // [{ id, label, note? }]

const active = ref(props.items[0]?.id);
let observer;
onMounted(() => {
    observer = new IntersectionObserver((entries) => {
        const visible = entries.filter((e) => e.isIntersecting).sort((a, b) => a.boundingClientRect.top - b.boundingClientRect.top);
        if (visible.length) active.value = visible[0].target.id;
    }, { rootMargin: '0px 0px -60% 0px' });
    props.items.forEach((i) => { const el = document.getElementById(i.id); if (el) observer.observe(el); });
});
onBeforeUnmount(() => observer?.disconnect());
</script>

<template>
    <nav aria-label="Settings sections" class="sticky top-6 hidden flex-[0_1_200px] flex-col gap-0.5 text-[13px] lg:flex">
        <a v-for="i in items" :key="i.id" :href="`#${i.id}`" :aria-current="active === i.id ? 'true' : null"
           class="flex items-center justify-between gap-2 rounded-md border px-3 py-2"
           :class="active === i.id ? 'border-line bg-surface font-semibold text-navy' : 'border-transparent font-medium text-ink-2 hover:bg-line-soft'"
           @click="active = i.id">
            {{ i.label }}
            <span v-if="i.note" class="text-[11px] font-semibold text-amber-text">{{ i.note }}</span>
        </a>
    </nav>
</template>
