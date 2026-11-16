<script setup>
// The Spacepad mark with its name, one size everywhere in the portal (the Blade layouts match it).
defineProps({
    href: { type: String, default: null },
    dark: { type: Boolean, default: false },
});
</script>

<template>
    <component :is="href ? 'a' : 'span'" :href="href" class="inline-flex items-center gap-2.5 text-lg leading-none font-bold tracking-tight"
               :class="dark ? 'text-white' : 'text-navy'">
        <img src="/images/spacepad-logo.svg" alt="" class="h-9 w-9 flex-none">
        Spacepad
    </component>
</template>
