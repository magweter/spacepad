<script setup>
import { Head, router } from '@inertiajs/vue3';
import Logo from '../../Components/Logo.vue';
import FlashMessage from '../../Components/FlashMessage.vue';
import Button from '../../DesignSystem/Button.vue';
import Card from '../../DesignSystem/Card.vue';
import Notice from '../../DesignSystem/Notice.vue';

const props = defineProps({ sent: Boolean, email: String, domain: String, workspace: Object, urls: Object });

const initials = (name) => (name || '?').split(' ').map((p) => p[0]).slice(0, 2).join('').toUpperCase();
const shown = props.workspace.members.slice(0, 4);
const more = props.workspace.members.length - shown.length;
const summary = [
    props.workspace.plan,
    `${props.workspace.displays} ${props.workspace.displays === 1 ? 'display' : 'displays'}`,
    props.workspace.createdBy ? `set up by ${props.workspace.createdBy} ${props.workspace.createdAgo}` : null,
].filter(Boolean).join(', ');
</script>

<template>
    <Head title="Join your team" />

    <div class="flex min-h-screen flex-col bg-ground text-sm leading-normal text-ink">
        <header class="flex items-center justify-between px-6 py-5 md:px-10">
            <Logo />
            <button type="button" class="text-[13px] font-semibold text-muted hover:text-navy" @click="router.post(urls.logout)">Log out</button>
        </header>

        <main class="flex flex-1 items-start justify-center px-4 pt-6 pb-16 md:pt-14">
            <div class="flex w-full max-w-[520px] flex-col gap-6">
                <FlashMessage />

                <div class="flex flex-col gap-1.5">
                    <span class="text-xs font-semibold tracking-[0.06em] text-muted uppercase">Your team is on Spacepad</span>
                    <h1 class="text-[28px] leading-tight font-bold tracking-tight">
                        {{ sent ? 'Your request is on its way' : `Join ${workspace.name}?` }}
                    </h1>
                    <p class="text-muted">
                        <template v-if="sent">We asked the admins of {{ workspace.name }} to let you in. You get an email at {{ email }} once they approve.</template>
                        <template v-else>Someone at {{ domain }} already set up a workspace. Join it to see and manage the same displays.</template>
                    </p>
                </div>

                <Card padding="lg">
                    <div class="flex flex-col gap-4">
                        <div class="flex items-center gap-3">
                            <span class="flex h-11 w-11 flex-none items-center justify-center rounded-[10px] bg-amber text-[17px] font-bold text-navy">{{ initials(workspace.name) }}</span>
                            <span class="flex min-w-0 flex-col">
                                <span class="truncate text-base font-bold">{{ workspace.name }}</span>
                                <span class="text-[13px] text-muted">{{ summary }}</span>
                            </span>
                        </div>
                        <div v-if="shown.length" class="flex items-center gap-2.5 border-t border-line-soft pt-4">
                            <span class="flex -space-x-2">
                                <span v-for="member in shown" :key="member.name" :title="member.name"
                                      class="flex h-8 w-8 items-center justify-center rounded-full border-2 border-surface bg-navy-50 text-xs font-semibold text-navy">{{ initials(member.name) }}</span>
                            </span>
                            <span class="text-[13px] text-muted">
                                {{ shown.map((m) => m.name).join(', ') }}<template v-if="more > 0"> and {{ more }} more</template>
                            </span>
                        </div>
                    </div>
                </Card>

                <Notice v-if="sent" tone="info" title="Waiting for approval">You can close this page. The link in the email brings you straight in.</Notice>

                <div class="flex flex-col gap-3">
                    <Button v-if="!sent" size="lg" @click="router.post(urls.request)">Ask to join</Button>
                    <button type="button" class="self-center text-[13px] font-semibold text-muted hover:text-navy" @click="router.post(urls.separate)">
                        {{ sent ? 'Set up a workspace of my own instead' : 'No thanks, set up a separate workspace' }}
                    </button>
                </div>
            </div>
        </main>
    </div>
</template>
