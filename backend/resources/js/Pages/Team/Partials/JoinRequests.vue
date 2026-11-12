<script setup>
// Colleagues who asked to join through their email domain. An owner or admin decides on the
// review page; nobody joins on their own.
defineProps({ requests: Array });
</script>

<template>
    <div class="sp-card flex flex-col gap-3 border-amber-300 bg-amber-50 px-[18px] py-4">
        <h2 class="text-[13px] font-semibold tracking-wide text-amber-text uppercase">Asked to join</h2>
        <div v-for="r in requests" :key="r.id" class="flex flex-wrap items-center gap-3 text-[13px]">
            <span class="flex min-w-0 flex-[1_1_200px] flex-col">
                <span class="truncate font-semibold">{{ r.name }}</span>
                <span class="text-xs text-muted">{{ r.email }}, {{ r.askedAt }}</span>
            </span>
            <a :href="r.url" class="sp-btn sp-btn-primary sp-btn-sm">Review</a>
        </div>
    </div>
</template>
