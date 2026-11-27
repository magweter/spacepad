<script setup>
// Connect a CalDAV server (Nextcloud, iCloud, Fastmail and the like) with a URL and credentials.
// Spacepad checks the connection before it saves anything.
import { Head, useForm } from '@inertiajs/vue3';
import PortalShell from '../../Components/PortalShell.vue';
import Icon from '../../Components/Icon.vue';
import Button from '../../DesignSystem/Button.vue';
import Card from '../../DesignSystem/Card.vue';
import Notice from '../../DesignSystem/Notice.vue';

const props = defineProps({ shell: Object, onboarding: Boolean, urls: Object });

const form = useForm({ url: '', username: '', password: '' });

function submit() {
    form.post(props.urls.store, { onError: () => form.reset('password') });
}

const field = 'w-full rounded-[10px] border border-offline-border bg-surface px-3.5 py-2.5 text-sm focus:border-navy focus:outline-none';
</script>

<template>
    <Head title="Connect CalDAV" />

    <PortalShell :shell="shell" title="Connect CalDAV">
        <div class="flex flex-col gap-6 px-6 pt-6 pb-10 md:px-10">
            <a :href="urls.back" class="inline-flex items-center gap-1.5 self-start text-[13px] font-semibold text-navy hover:underline">
                <Icon name="arrowLeft" :size="14" />{{ onboarding ? 'Back to the setup' : 'Back to calendars' }}
            </a>


            <Card padding="lg" class="max-w-[560px]">
                <form class="flex flex-col gap-5" @submit.prevent="submit">
                    <div class="flex flex-col gap-1">
                        <h2 class="font-display text-lg font-bold tracking-tight">Enter your server details</h2>
                        <p class="text-muted">Spacepad connects to your server to read your calendars.</p>
                    </div>

                    <Notice v-if="form.errors.connection" tone="danger" title="Could not connect">{{ form.errors.connection }}</Notice>

                    <label class="flex flex-col gap-1.5">
                        <span class="text-[13px] font-semibold">Server URL</span>
                        <input v-model="form.url" type="url" name="url" required autocomplete="url" placeholder="https://example.com/remote.php/dav" :class="field">
                        <span v-if="form.errors.url" class="text-xs text-reserved-text">{{ form.errors.url }}</span>
                        <span v-else class="text-xs text-muted">The address of your CalDAV server.</span>
                    </label>

                    <label class="flex flex-col gap-1.5">
                        <span class="text-[13px] font-semibold">Username</span>
                        <input v-model="form.username" type="text" name="username" required autocomplete="username" :class="field">
                        <span v-if="form.errors.username" class="text-xs text-reserved-text">{{ form.errors.username }}</span>
                    </label>

                    <label class="flex flex-col gap-1.5">
                        <span class="text-[13px] font-semibold">Password</span>
                        <input v-model="form.password" type="password" name="password" required autocomplete="current-password" :class="field">
                        <span v-if="form.errors.password" class="text-xs text-reserved-text">{{ form.errors.password }}</span>
                        <span v-else class="text-xs text-muted">An app password works best, where your server offers one.</span>
                    </label>

                    <div class="flex flex-wrap items-center justify-end gap-2 border-t border-line-soft pt-4">
                        <Button variant="ghost" :href="urls.back" external>Cancel</Button>
                        <Button type="submit" :disabled="form.processing">{{ form.processing ? 'Checking the connection' : 'Connect' }}</Button>
                    </div>
                </form>
            </Card>
        </div>
    </PortalShell>
</template>
