<script setup>
defineProps({
    href: { type: String, default: null },
    dark: { type: Boolean, default: false },
    small: { type: Boolean, default: false },
});
</script>

<template>
    <component :is="href ? 'a' : 'span'" :href="href" class="inline-flex items-center font-bold"
               :class="[dark ? 'text-white' : 'text-navy', small ? 'gap-2 text-sm' : 'gap-2.5 text-base']">
        <img src="/images/spacepad-logo.svg" alt="" :class="small ? 'h-6 w-6' : 'h-8 w-8'">
        Spacepad
    </component>
</template>
