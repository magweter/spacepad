<script setup>
// Invitations that have not been accepted yet. Resending makes a fresh link and the old one stops
// working; withdrawing is never Pro-gated.
import { router } from '@inertiajs/vue3';

defineProps({ invitations: Array, lifetimeDays: Number, canResend: Boolean });

const resend = (i) => router.post(i.urls.resend, {}, { preserveScroll: true });
function withdraw(i) {
    if (confirm(`Withdraw the invitation for ${i.email}?`)) router.delete(i.urls.destroy, { preserveScroll: true });
}
</script>

<template>
    <div class="sp-card flex flex-col gap-3 px-[18px] py-4">
        <div class="flex items-center justify-between">
            <h2 class="text-[13px] font-semibold tracking-wide text-muted uppercase">Pending invitations</h2>
            <span class="text-xs text-muted">Expire after {{ lifetimeDays }} days</span>
        </div>
        <p v-if="!invitations.length" class="text-[13px] text-muted">No pending invitations.</p>
        <template v-for="(i, index) in invitations" :key="i.id">
            <div v-if="index" class="h-px bg-line-soft"></div>
            <div class="flex flex-wrap items-center gap-3 text-[13px]">
                <span class="flex min-w-0 flex-[1_1_200px] flex-col">
                    <span class="truncate font-semibold">{{ i.email }}</span>
                    <span class="text-xs text-muted">{{ i.roleLabel }}, sent {{ i.sent }}<template v-if="i.invitedBy"> by {{ i.invitedBy }}</template>, expires {{ i.expires }}</span>
                </span>
                <button v-if="canResend" type="button" class="sp-btn sp-btn-secondary sp-btn-sm" @click="resend(i)">Resend</button>
                <button type="button" class="px-1 py-1 text-xs font-semibold text-reserved-text hover:underline" @click="withdraw(i)">Withdraw</button>
            </div>
        </template>
    </div>
</template>
