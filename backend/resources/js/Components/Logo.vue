<script setup>
defineProps({ href: { type: String, default: null }, dark: { type: Boolean, default: false } });
</script>

<template>
    <component :is="href ? 'a' : 'span'" :href="href" class="inline-flex items-center gap-2.5 text-base font-bold" :class="dark ? 'text-white' : 'text-navy'">
        <img src="/images/logo-black.svg" alt="" class="h-8 w-8 flex-none rounded-lg" :class="dark ? 'bg-white p-1' : ''">
        Spacepad
    </component>
</template>
