<script setup>
import { computed, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import StepFooter from '../../../Components/StepFooter.vue';

defineOptions({ inheritAttrs: false });

const props = defineProps({
    answers: Object, accounts: Array, urls: Object, next: String, previous: String, onboarding: Object,
});
defineEmits(['skip-setup']);

const page = usePage();
const providers = computed(() => page.props.app.providers);
const allowBooking = ref(false);

// The answer from "About you" decides which provider comes first.
const order = computed(() => {
    const all = [
        { key: 'microsoft', enabled: providers.value.microsoft },
        { key: 'google', enabled: providers.value.google },
        { key: 'caldav', enabled: providers.value.caldav },
    ].filter((p) => p.enabled);
    const preferred = props.answers.calendar_provider;
    return [...all.filter((p) => p.key === preferred), ...all.filter((p) => p.key !== preferred)];
});

const copy = {
    microsoft: {
        title: 'Connect Microsoft 365',
        body: 'Start read-only with your own account, no admin consent needed. Booking from the display can be switched on later.',
        badge: '2 minutes',
    },
    google: {
        title: 'Connect Google Workspace',
        body: 'Sign in with Google and pick the room calendars. Read-only is enough to show what is booked.',
        badge: '2 minutes',
    },
    caldav: {
        title: 'Connect CalDAV',
        body: 'Nextcloud, Fastmail, iCloud or any other CalDAV server. You need its address, a username and a password.',
        badge: 'Server address',
    },
};

const connected = computed(() => props.accounts[0] ?? null);
const csrf = props.onboarding.csrf();
</script>

<template>
    <div class="flex flex-col gap-2">
        <h1 class="text-[28px] leading-tight font-bold tracking-tight">{{ connected ? 'Your calendar is connected' : 'Where do your rooms live?' }}</h1>
        <p class="text-muted">
            {{ connected ? 'Next, pick the room for your first display.' : 'Connect the calendar your meeting rooms are in. You come straight back here afterwards.' }}
        </p>
    </div>

    <div v-if="connected" class="flex items-center gap-3 rounded-xl border border-line bg-white p-4">
        <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-available-bg text-available-text">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5" /></svg>
        </span>
        <span class="flex flex-col">
            <span class="font-semibold">{{ connected.providerLabel }}</span>
            <span class="text-[13px] text-muted">{{ connected.email || connected.name }}<template v-if="connected.readOnly"> · read-only</template></span>
        </span>
    </div>

    <div v-else class="flex flex-col gap-3">
        <template v-for="provider in order" :key="provider.key">
            <form v-if="provider.key !== 'caldav'" method="POST" :action="provider.key === 'microsoft' ? urls.outlookAuth : urls.googleAuth"
                  class="flex flex-col gap-3 rounded-xl border border-line bg-white p-5">
                <input type="hidden" name="_token" :value="csrf">
                <input type="hidden" name="onboarding" value="1">
                <input type="hidden" name="permission_type" :value="allowBooking ? 'write' : 'read'">
                <div class="flex items-start justify-between gap-4">
                    <span class="flex flex-col gap-1">
                        <span class="font-semibold">{{ copy[provider.key].title }}</span>
                        <span class="text-[13px] text-muted">{{ copy[provider.key].body }}</span>
                    </span>
                    <span class="flex-none rounded-full bg-line-soft px-2.5 py-0.5 text-[11px] font-semibold text-ink-3">{{ copy[provider.key].badge }}</span>
                </div>
                <div class="flex flex-wrap items-center gap-4">
                    <button type="submit" class="rounded-[10px] bg-navy px-4 py-2.5 text-[13px] font-semibold text-white hover:bg-navy-800">
                        {{ provider.key === 'microsoft' ? 'Sign in with Microsoft' : 'Sign in with Google' }}
                    </button>
                    <label class="flex items-center gap-2 text-[13px] text-ink-3">
                        <input v-model="allowBooking" type="checkbox" class="h-4 w-4 rounded border-slate-300">
                        Also let people book from the display
                    </label>
                </div>
            </form>

            <div v-else class="flex flex-col gap-3 rounded-xl border border-line bg-white p-5">
                <div class="flex items-start justify-between gap-4">
                    <span class="flex flex-col gap-1">
                        <span class="font-semibold">{{ copy.caldav.title }}</span>
                        <span class="text-[13px] text-muted">{{ copy.caldav.body }}</span>
                    </span>
                    <span class="flex-none rounded-full bg-line-soft px-2.5 py-0.5 text-[11px] font-semibold text-ink-3">{{ copy.caldav.badge }}</span>
                </div>
                <a :href="urls.caldavCreate" class="self-start rounded-[10px] border border-slate-300 bg-white px-4 py-2.5 text-[13px] font-semibold text-navy hover:border-navy">Enter CalDAV details</a>
            </div>
        </template>

        <p v-if="!order.length" class="rounded-xl border border-line bg-white p-5 text-[13px] text-muted">
            No calendar provider is enabled on this instance. Ask your administrator to configure Microsoft, Google or CalDAV.
        </p>
    </div>

    <StepFooter :previous="previous" :show-skip="!connected" skip-label="Connect later"
                :continue-label="connected ? 'Pick a room' : 'Continue'" :continue-disabled="!connected"
                @back="onboarding.goTo(previous)" @skip="onboarding.skipAndGo('calendar', 'ready')"
                @skip-setup="$emit('skip-setup')" @continue="onboarding.completeAndGo('calendar', 'room')" />
</template>
