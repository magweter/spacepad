<script setup>
// Spacepad Design System: Button. Markup and classes follow components/bundle.css (sp-btn).
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    variant: { type: String, default: 'primary' }, // primary | secondary | accent | ghost | danger
    size: { type: String, default: 'md' }, // sm | md | lg
    href: { type: String, default: null },
    external: { type: Boolean, default: false },
    type: { type: String, default: 'button' },
    disabled: { type: Boolean, default: false },
});

const classes = computed(() => ['sp-btn', `sp-btn-${props.variant}`, `sp-btn-${props.size}`]);
</script>

<template>
    <a v-if="href && external" :href="href" :class="classes"><span v-if="$slots.icon" class="sp-btn-icon"><slot name="icon" /></span><slot /></a>
    <Link v-else-if="href" :href="href" :class="classes"><span v-if="$slots.icon" class="sp-btn-icon"><slot name="icon" /></span><slot /></Link>
    <button v-else :type="type" :disabled="disabled" :class="classes"><span v-if="$slots.icon" class="sp-btn-icon"><slot name="icon" /></span><slot /></button>
</template>
