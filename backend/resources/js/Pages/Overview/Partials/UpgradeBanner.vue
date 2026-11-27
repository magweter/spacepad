<script setup>
// The upgrade banner on a free workspace (design 01d): what Pro adds and what it costs, in the
// amber of the demo banner. Prices as on the website: per display, never per user.
import { openUpgrade } from '../../../Components/Upgrade/upgrade';
import Icon from '../../../Components/Icon.vue';

const props = defineProps({ selfHosted: Boolean, pricingUrl: { type: String, required: true } });
const price = props.selfHosted ? '$5' : '$8';
</script>

<template>
    <section aria-label="Upgrade to Pro" class="flex flex-wrap items-center gap-5 rounded-xl border border-amber-300 bg-amber-50 px-7 py-6 text-navy">
        <span class="inline-flex h-10 w-10 flex-none items-center justify-center rounded-[10px] bg-amber"><Icon name="sparkles" :size="18" /></span>
        <span class="flex min-w-0 flex-[1_1_320px] flex-col">
            <span class="text-[15px] font-semibold">Unlock all features with Pro.</span>
            <span class="text-[13px] text-ink-3">More than one display, overview boards, booking at the door, check-in and your own branding. Per display, never per user, from {{ price }} a month.</span>
        </span>
        <button type="button" class="sp-btn sp-btn-primary sp-btn-md" @click="openUpgrade('displays')">Upgrade to Pro</button>
        <a :href="pricingUrl" target="_blank" rel="noopener" class="sp-btn sp-btn-secondary sp-btn-md border-amber-300">See all Pro features</a>
    </section>
</template>
