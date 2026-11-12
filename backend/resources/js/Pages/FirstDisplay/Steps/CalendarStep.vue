<script setup>
import { computed, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import StepHeading from '../../../Components/StepHeading.vue';
import Button from '../../../DesignSystem/Button.vue';
import Card from '../../../DesignSystem/Card.vue';

defineOptions({ inheritAttrs: false });

const props = defineProps({ accounts: Array, urls: Object, eyebrow: String, flow: Object });

const page = usePage();
const providers = computed(() => page.props.app.providers);
const oauth = computed(() => [
    { key: 'microsoft', enabled: providers.value.microsoft, tile: 'M', title: 'Connect Microsoft 365', label: 'Microsoft 365', action: props.urls.outlookAuth,
      body: 'Sign in with your own account. Read-only works without admin consent; booking from the display can be switched on later.' },
    { key: 'google', enabled: providers.value.google, tile: 'G', title: 'Connect Google Workspace', label: 'Google Workspace', action: props.urls.googleAuth,
      body: 'Sign in with Google and pick the room calendars. Read-only is enough to show what is booked.' },
].filter((p) => p.enabled));

const picked = ref(oauth.value[0]?.key ?? null);
const allowBooking = ref(false);
const form = ref(null);
const chosen = computed(() => oauth.value.find((p) => p.key === picked.value));
const connected = computed(() => props.accounts[0] ?? null);
const csrf = props.flow.csrf();
</script>

<template>
    <template v-if="connected">
        <StepHeading :eyebrow="eyebrow" title="Your calendar is connected">Next, pick the room for this display.</StepHeading>
        <Card rule="available">
            <span class="flex flex-col">
                <span class="font-semibold">{{ connected.providerLabel }}</span>
                <span class="text-[13px] text-muted">{{ connected.email || connected.name }}<template v-if="connected.readOnly">, read-only</template></span>
            </span>
        </Card>
        <div class="flex items-center justify-end">
            <Button size="lg" @click="flow.completeAndGo('calendar', 'room')">Pick a room</Button>
        </div>
    </template>

    <template v-else>
        <StepHeading :eyebrow="eyebrow" title="Where do your room calendars live?">
            No admin rights? Start read-only and switch on booking later.
        </StepHeading>

        <div class="flex flex-col gap-3">
            <button v-for="provider in oauth" :key="provider.key" type="button" :aria-pressed="picked === provider.key" @click="picked = provider.key"
                    class="flex items-start gap-4 rounded-[14px] border-2 bg-surface px-5 py-[18px] text-left text-ink transition-colors hover:border-navy"
                    :class="picked === provider.key ? 'border-navy' : 'border-line'">
                <span class="flex h-11 w-11 flex-none items-center justify-center rounded-[10px] bg-info-bg text-[17px] font-bold text-info">{{ provider.tile }}</span>
                <span class="flex flex-1 flex-col gap-[3px]">
                    <span class="text-base font-bold">{{ provider.title }}</span>
                    <span class="text-[13px] text-muted">{{ provider.body }}</span>
                </span>
                <span class="rounded-full bg-line-soft px-[9px] py-[3px] text-[11px] font-bold whitespace-nowrap text-ink-3">2 minutes</span>
            </button>
            <a v-if="providers.caldav" :href="urls.caldavCreate" class="self-start text-[13px] font-semibold text-navy hover:underline">
                Using Nextcloud, iCloud or another CalDAV server? →
            </a>
            <p v-if="!oauth.length && !providers.caldav" class="text-[13px] text-muted">
                No calendar provider is enabled on this instance. Ask your administrator to configure Microsoft, Google or CalDAV.
            </p>
        </div>

        <!-- A real form post: the OAuth redirect leaves the app, which an Inertia visit cannot follow. -->
        <form v-if="chosen" ref="form" method="POST" :action="chosen.action" class="flex flex-wrap items-center justify-between gap-3">
            <input type="hidden" name="_token" :value="csrf">
            <input type="hidden" name="onboarding" value="1">
            <input type="hidden" name="permission_type" :value="allowBooking ? 'write' : 'read'">
            <label class="flex items-center gap-2 text-[13px] text-ink-3">
                <input v-model="allowBooking" type="checkbox" class="h-4 w-4 rounded border-offline-border">
                Also let people book from the display
            </label>
            <div class="flex items-center gap-2">
                <Button variant="ghost" size="lg" :href="urls.dashboard" external>Do this later</Button>
                <Button type="submit" size="lg">Sign in with {{ chosen.label }}</Button>
            </div>
        </form>
    </template>
</template>
