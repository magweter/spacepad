<script setup>
import { Head } from '@inertiajs/vue3';
import PortalShell from '../../Components/PortalShell.vue';
import FlashMessage from '../../Components/FlashMessage.vue';
import Chip from '../../DesignSystem/Chip.vue';
import SettingRow from '../../DesignSystem/SettingRow.vue';
import DeleteAccount from './Partials/DeleteAccount.vue';

defineProps({ shell: Object, account: Object, workspaces: Array, urls: Object });
</script>

<template>
    <Head title="Account" />

    <component :is="shell ? PortalShell : 'div'" v-bind="shell ? { shell, title: 'Account' } : { class: 'min-h-screen bg-ground text-sm text-ink' }">
        <div class="flex max-w-[808px] flex-col gap-4 px-6 pt-7 pb-10 md:px-8">
            <p class="-mt-2 text-[13px] text-muted">Your personal account. The same whichever workspace you have open.</p>

            <FlashMessage />

            <section class="sp-card gap-0 px-[18px] py-1.5">
                <div class="flex flex-col gap-0.5 border-b border-line pt-3 pb-1.5">
                    <h2 class="text-[15px] font-semibold">Account details</h2>
                    <span class="text-xs text-muted">You sign in with this email address.</span>
                </div>
                <SettingRow label="Name"><span class="text-[13px] font-medium">{{ account.name }}</span></SettingRow>
                <SettingRow label="Email"><span class="text-[13px] font-medium">{{ account.email }}</span></SettingRow>
                <SettingRow label="Member since"><span class="text-[13px] font-medium">{{ account.memberSince ?? '-' }}</span></SettingRow>
            </section>

            <section v-if="workspaces.length" class="sp-card gap-0 px-[18px] py-1.5">
                <div class="flex flex-col gap-0.5 border-b border-line pt-3 pb-1.5">
                    <h2 class="text-[15px] font-semibold">Your workspaces</h2>
                    <span class="text-xs text-muted">Switch between them from the menu at the top left.</span>
                </div>
                <SettingRow v-for="w in workspaces" :key="w.id" :label="w.name" :help="w.current ? 'Open now' : null">
                    <Chip :tone="w.role === 'Owner' ? 'amber' : w.role === 'Admin' ? 'navy' : 'neutral'">{{ w.role }}</Chip>
                </SettingRow>
            </section>

            <DeleteAccount :email="account.email" :url="urls.destroy" />
        </div>
    </component>
</template>
