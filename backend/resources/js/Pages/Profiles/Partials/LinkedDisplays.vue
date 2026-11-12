<script setup>
// The displays that follow a profile, and which sections each one holds on its own.
import { Link } from '@inertiajs/vue3';
import Chip from '../../../DesignSystem/Chip.vue';

defineProps({
    displays: { type: Array, required: true },
    displaysUrl: { type: String, required: true },
});
</script>

<template>
    <div class="sp-card min-w-0 overflow-hidden">
        <div class="flex items-center justify-between border-b border-line px-[18px] py-3.5">
            <h3 class="text-sm font-semibold">Displays on this profile</h3>
            <span class="text-xs text-muted">{{ displays.length }}</span>
        </div>
        <p v-if="!displays.length" class="px-[18px] py-4 text-[13px] text-muted">
            No display follows this profile yet. Select displays on the
            <Link :href="displaysUrl" class="font-semibold text-navy hover:underline">Displays page</Link> and assign it there.
        </p>
        <ul v-else>
            <li v-for="d in displays" :key="d.id" class="border-b border-line-soft last:border-b-0">
                <Link :href="d.url" class="flex items-center gap-3 px-[18px] py-[11px] text-[13px] hover:bg-surface-raised">
                    <span class="min-w-0 flex-1 truncate font-semibold">{{ d.name }}</span>
                    <Chip v-if="d.ownSections.length" tone="amber" :title="d.ownSections.join(', ')">
                        {{ d.ownSections.length === 1 ? `Own ${d.ownSections[0].toLowerCase()}` : `${d.ownSections.length} sections its own` }}
                    </Chip>
                    <span v-else class="text-[11px] text-muted">Follows profile</span>
                </Link>
            </li>
        </ul>
        <div v-if="displays.length" class="bg-surface-raised px-[18px] py-3 text-xs text-muted">
            A section a display holds on its own stays as it is when you save the profile.
            Assign or unlink displays on the <Link :href="displaysUrl" class="font-semibold text-navy hover:underline">Displays page</Link>.
        </div>
    </div>
</template>
