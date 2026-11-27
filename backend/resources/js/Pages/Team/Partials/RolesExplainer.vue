<script setup>
// What each role may do, as WorkspaceRole and WorkspacePolicy decide it.
const roles = [
    { name: 'Owner', body: 'Everything, including changing roles, removing members and billing.' },
    { name: 'Admin', body: 'Invites colleagues and manages billing, plus everything a member does.' },
    { name: 'Member', body: 'Creates and configures displays, boards, profiles and calendars. No billing or team access.' },
];
</script>

<template>
    <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
        <div v-for="r in roles" :key="r.name" class="sp-card flex flex-col gap-1 px-4 py-3.5 text-[13px]">
            <span class="font-semibold">{{ r.name }}</span>
            <span class="text-xs text-muted">{{ r.body }}</span>
        </div>
    </div>
</template>
