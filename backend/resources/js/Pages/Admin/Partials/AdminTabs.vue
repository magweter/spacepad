<script setup>
// The admin panel's tabs and the figures above every admin screen.
defineProps({ active: { type: String, required: true }, stats: { type: Object, required: true }, pendingRoadmap: { type: Number, default: 0 } });

const tabs = [
    { key: 'users', label: 'Users', href: '/admin' },
    { key: 'workspaces', label: 'Workspaces', href: '/admin/workspaces' },
    { key: 'merge', label: 'Merge workspaces', href: '/admin/merge' },
    { key: 'roadmap', label: 'Roadmap', href: '/admin/roadmap' },
];
const figures = (s) => [
    { label: 'Total users', value: s.total_users },
    { label: 'Active workspaces', value: s.active_workspaces },
    { label: 'Active instances', value: s.active_instances },
    { label: 'Total instances', value: s.total_instances },
];
</script>

<template>
    <div class="flex flex-col gap-5">
        <section aria-label="Figures" class="sp-card">
            <div class="grid grid-cols-2 py-1 lg:grid-cols-4">
                <div v-for="(f, i) in figures(stats)" :key="f.label" class="flex flex-col gap-1 px-5 py-3.5" :class="i < 3 ? 'lg:border-r lg:border-line-soft' : ''">
                    <span class="text-[13px] font-medium text-muted">{{ f.label }}</span>
                    <span class="font-display text-[26px] leading-none font-bold tracking-[-0.03em] tabular-nums">{{ f.value }}</span>
                </div>
            </div>
        </section>
        <nav aria-label="Admin" class="flex flex-wrap gap-1 border-b border-line">
            <a v-for="t in tabs" :key="t.key" :href="t.href" :aria-current="active === t.key ? 'page' : null"
               class="-mb-px inline-flex items-center gap-2 border-b-2 px-3 py-2.5 text-[13px] font-semibold"
               :class="active === t.key ? 'border-navy text-ink' : 'border-transparent text-muted hover:text-ink'">
                {{ t.label }}
                <span v-if="t.key === 'roadmap' && pendingRoadmap" class="rounded-full bg-checkin-bg px-1.5 py-px text-[11px] font-semibold text-checkin-text">{{ pendingRoadmap }}</span>
            </a>
        </nav>
    </div>
</template>
