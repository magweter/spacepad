<script setup>
// How the units on the plan card are counted. Members never cost anything.
defineProps({ billing: Object });
</script>

<template>
    <div class="sp-card flex flex-col gap-2.5 px-[18px] py-4 text-[13px]">
        <h2 class="text-[13px] font-semibold tracking-wide text-muted uppercase">What counts as a unit</h2>
        <div class="flex justify-between"><span>Displays</span><span class="font-semibold">{{ billing.usage.displays }} {{ billing.usage.displays === 1 ? 'unit' : 'units' }}</span></div>
        <div class="flex justify-between"><span>Boards <span class="text-muted">(count double)</span></span><span class="font-semibold">{{ billing.usage.boards * 2 }} {{ billing.usage.boards * 2 === 1 ? 'unit' : 'units' }}</span></div>
        <div class="flex justify-between"><span>Team members</span><span class="font-semibold text-available-text">No charge</span></div>
        <span class="text-xs text-muted">Usage for this workspace. Displays count once, boards count double. There is no charge per team member.</span>
    </div>
</template>
