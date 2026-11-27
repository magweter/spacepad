<script setup>
import { Head, router } from '@inertiajs/vue3';
import Logo from '../../Components/Logo.vue';
import Button from '../../DesignSystem/Button.vue';
import Card from '../../DesignSystem/Card.vue';
import Notice from '../../DesignSystem/Notice.vue';

defineProps({ request: Object, workspace: Object, canDecide: Boolean, needsPro: Boolean, urls: Object });
</script>

<template>
    <Head title="Join request" />

    <div class="flex min-h-screen flex-col bg-ground text-sm leading-normal text-ink">
        <header class="px-6 py-5 md:px-10"><Logo :href="urls.dashboard" /></header>

        <main class="flex flex-1 items-start justify-center px-4 pt-6 pb-16 md:pt-14">
            <div class="flex w-full max-w-[520px] flex-col gap-6">

                <div class="flex flex-col gap-1.5">
                    <span class="text-xs font-semibold tracking-[0.06em] text-muted uppercase">{{ workspace.name }}</span>
                    <h1 class="text-[28px] leading-tight font-bold tracking-tight">{{ request.name }} wants to join</h1>
                    <p class="text-muted">They signed up with {{ request.email }}, {{ request.askedAt }}.</p>
                </div>

                <Card>
                    <span class="flex flex-col">
                        <span class="font-semibold">{{ request.name }}</span>
                        <span class="text-[13px] text-muted">{{ request.email }}, joins as member</span>
                    </span>
                </Card>

                <Notice v-if="request.status === 'approved'" tone="success" title="Approved">They are a member of {{ workspace.name }}.</Notice>
                <Notice v-else-if="request.status === 'declined'" tone="neutral" title="Declined">They keep a workspace of their own.</Notice>
                <Notice v-else-if="!canDecide" tone="neutral" title="An owner or admin decides">Ask one of them to approve this request.</Notice>
                <Notice v-else-if="needsPro" tone="warning" title="Team members are part of Pro" action="See plans" :href="urls.upgrade">
                    Upgrade to add {{ request.name }} to this workspace.
                </Notice>

                <div class="flex items-center justify-between gap-2">
                    <Button variant="ghost" :href="urls.dashboard" external>Go to dashboard</Button>
                    <div v-if="canDecide && request.status === 'pending'" class="flex items-center gap-2">
                        <Button variant="secondary" @click="router.post(urls.decline)">Decline</Button>
                        <Button :disabled="needsPro" @click="router.post(urls.approve)">Approve</Button>
                    </div>
                </div>
            </div>
        </main>
    </div>
</template>
