<script setup>
// "Needs attention" (design 01d): one card with AttentionRows divided by a line, no heading: each
// row's tag already says whether action is needed. Shown only when something needs doing.
</script>

<template>
    <section aria-label="Needs attention" class="sp-card gap-0 overflow-hidden">
        <div class="flex flex-col divide-y divide-line-soft">
            <slot />
        </div>
    </section>
</template>
