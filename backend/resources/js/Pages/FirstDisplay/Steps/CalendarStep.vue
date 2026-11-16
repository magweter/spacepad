<script setup>
import { computed, ref } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import StepHeading from '../../../Components/StepHeading.vue';
import Button from '../../../DesignSystem/Button.vue';
import Card from '../../../DesignSystem/Card.vue';

defineOptions({ inheritAttrs: false });

const props = defineProps({ accounts: Array, urls: Object, eyebrow: String, flow: Object });

const page = usePage();
const providers = computed(() => page.props.app.providers);
const oauth = computed(() => [
    { key: 'microsoft', enabled: providers.value.microsoft, tile: 'M', title: 'Microsoft 365', label: 'Microsoft 365', action: props.urls.outlookAuth,
      body: 'Outlook and Exchange room mailboxes. Sign in with your own account.' },
    { key: 'google', enabled: providers.value.google, tile: 'G', title: 'Google Workspace', label: 'Google Workspace', action: props.urls.googleAuth,
      body: 'Google Calendar and room resources. Sign in with your own account.' },
].filter((p) => p.enabled));

const picked = ref(oauth.value[0]?.key ?? null);
const permission = ref('read');
const chosen = computed(() => oauth.value.find((p) => p.key === picked.value));
const connected = computed(() => props.accounts[0] ?? null);
const csrf = props.flow.csrf();

const askIt = ref(false);
const itForm = useForm({ email: '' });

function sendItRequest() {
    itForm.post(props.urls.itRequest, { preserveScroll: true, onSuccess: () => { askIt.value = false; itForm.reset(); } });
}
</script>

<template>
    <template v-if="connected">
        <StepHeading :eyebrow="eyebrow" title="Your calendar is connected">Next, pick the room for this display.</StepHeading>
        <Card rule="available">
            <span class="flex flex-col">
                <span class="font-semibold">{{ connected.providerLabel }}</span>
                <span class="text-[13px] text-muted">{{ connected.email || connected.name }}, {{ connected.readOnly ? 'read only' : 'read and book' }}</span>
            </span>
        </Card>
        <div class="flex items-center justify-end">
            <Button size="lg" @click="flow.completeAndGo('calendar', 'room')">Pick a room</Button>
        </div>
    </template>

    <template v-else>
        <StepHeading :eyebrow="eyebrow" title="Where do your room calendars live?">
            Pick your calendar and what we are allowed to do with it.
        </StepHeading>

        <!-- Calendar -->
        <div v-if="oauth.length" class="grid gap-3 sm:grid-cols-2">
            <button v-for="provider in oauth" :key="provider.key" type="button" :aria-pressed="picked === provider.key" @click="picked = provider.key"
                    class="flex items-start gap-3.5 rounded-[14px] border-2 bg-surface p-[18px] text-left text-ink transition-colors hover:border-navy"
                    :class="picked === provider.key ? 'border-navy' : 'border-line'">
                <span class="flex h-11 w-11 flex-none items-center justify-center rounded-[10px] bg-info-bg text-[17px] font-bold text-info">{{ provider.tile }}</span>
                <span class="flex flex-1 flex-col gap-[3px]">
                    <span class="text-base font-bold">{{ provider.title }}</span>
                    <span class="text-[13px] text-muted">{{ provider.body }}</span>
                </span>
            </button>
        </div>

        <!-- What Spacepad may do -->
        <div v-if="oauth.length" class="flex flex-col gap-2.5">
            <span class="text-[13px] font-semibold">What may Spacepad do?</span>
            <div class="grid gap-3 sm:grid-cols-2">
                <button type="button" :aria-pressed="permission === 'read'" @click="permission = 'read'"
                        class="flex flex-col gap-1 rounded-lg border-2 bg-surface px-4 py-3.5 text-left transition-colors hover:border-navy"
                        :class="permission === 'read' ? 'border-navy bg-surface-raised' : 'border-line'">
                    <span class="flex items-center justify-between gap-2"><span class="font-bold">Read only</span><span class="rounded-full bg-line-soft px-2 py-0.5 text-[11px] font-bold text-ink-3">2 minutes</span></span>
                    <span class="text-[13px] text-muted">Shows what is booked. Works without admin consent.</span>
                </button>
                <button type="button" :aria-pressed="permission === 'write'" @click="permission = 'write'"
                        class="flex flex-col gap-1 rounded-lg border-2 bg-surface px-4 py-3.5 text-left transition-colors hover:border-navy"
                        :class="permission === 'write' ? 'border-navy bg-surface-raised' : 'border-line'">
                    <span class="font-bold">Read and book</span>
                    <span class="text-[13px] text-muted">People can also book a free room from the display. Often needs an admin to approve.</span>
                </button>
            </div>
        </div>

        <a v-if="providers.caldav" :href="urls.caldavCreate" class="self-start text-[13px] font-semibold text-navy hover:underline">
            Using Nextcloud, iCloud or another CalDAV server? →
        </a>
        <p v-if="!oauth.length && !providers.caldav" class="text-[13px] text-muted">
            No calendar provider is enabled on this instance. Ask your administrator to configure Microsoft, Google or CalDAV.
        </p>

        <!-- What to expect -->
        <div class="flex gap-3.5 rounded-lg border border-available-border bg-available-bg/40 p-4">
            <span class="flex h-9 w-9 flex-none items-center justify-center rounded-full bg-available-bg text-available-text">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z" /><path d="m9 12 2 2 4-4" /></svg>
            </span>
            <div class="flex flex-col gap-1.5 text-[13px]">
                <span class="font-semibold text-ink">Your calendar stays yours</span>
                <p class="text-ink-3">
                    Spacepad only reads the calendars you pick for a display. It keeps today's bookings so the display stays fast, and deletes them when the day is over.
                    It never changes your calendar, unless you choose read and book.
                </p>
                <span class="text-muted">Rather not connect yet? <a :href="urls.dashboard" class="font-semibold text-navy hover:underline">Look around first</a>.</span>
            </div>
        </div>

        <!-- No admin rights -->
        <div class="flex flex-col gap-3 rounded-lg border border-line bg-surface p-4">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <span class="flex flex-col">
                    <span class="text-[13px] font-semibold">No admin rights?</span>
                    <span class="text-[13px] text-muted">We email your IT admin what Spacepad reads and a link to approve it.</span>
                </span>
                <Button v-if="!askIt" variant="secondary" @click="askIt = true">Ask IT</Button>
            </div>
            <form v-if="askIt" class="flex flex-wrap items-start gap-2" @submit.prevent="sendItRequest">
                <div class="flex min-w-0 flex-1 flex-col gap-1">
                    <input v-model="itForm.email" type="email" required placeholder="it@company.com" aria-label="IT admin email"
                           class="rounded-[10px] border border-offline-border bg-surface px-3.5 py-2.5 text-sm focus:border-navy focus:outline-none">
                    <span v-if="itForm.errors.email" class="text-xs text-reserved-text">{{ itForm.errors.email }}</span>
                </div>
                <Button type="submit" :disabled="itForm.processing">Send request</Button>
            </form>
        </div>

        <!-- A real form post: the OAuth redirect leaves the app, which an Inertia visit cannot follow. -->
        <form v-if="chosen" method="POST" :action="chosen.action" class="flex flex-wrap items-center justify-end gap-2">
            <input type="hidden" name="_token" :value="csrf">
            <input type="hidden" name="onboarding" value="1">
            <input type="hidden" name="permission_type" :value="permission">
            <Button type="submit" size="lg">Sign in with {{ chosen.label }}</Button>
        </form>
    </template>
</template>
