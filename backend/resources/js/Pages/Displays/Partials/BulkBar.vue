<script setup>
// Configure several displays at once by linking them to a profile (Pro, more than one display).
import { computed, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import Button from '../../../DesignSystem/Button.vue';

const props = defineProps({ picked: Array, displays: Array, profiles: Array, showHint: Boolean, urls: Object });
const emit = defineEmits(['update:picked']);

const page = usePage();
const error = computed(() => page.props.errors?.display_profile_id || page.props.errors?.display_ids || null);
const profileId = ref('');
const sending = ref(false);
const count = computed(() => props.picked.length);
const noun = computed(() => (count.value === 1 ? 'display' : 'displays'));
// How many of the selected displays would lose their own settings.
const customised = computed(() => props.displays.filter((d) => d.hasOwnSettings && props.picked.includes(d.id)).length);

const clear = () => emit('update:picked', []);

function submit(id) {
    // Unlinking keeps a display's values, so only assigning needs a confirmation.
    if (id !== '' && customised.value > 0) {
        const n = customised.value;
        if (!confirm(`${n} selected ${n === 1 ? 'display has' : 'displays have'} their own settings. Assigning this profile clears them so they follow the profile. Continue?`)) return;
    }
    router.post(props.urls.bulk, { display_ids: props.picked, display_profile_id: id }, {
        preserveScroll: true,
        onStart: () => { sending.value = true; },
        onFinish: () => { sending.value = false; },
        onSuccess: () => { clear(); profileId.value = ''; },
    });
}
</script>

<template>
    <div v-if="!count && showHint" class="flex items-start gap-2 rounded-xl border border-dashed border-line bg-surface-raised px-4 py-3 text-[13px] text-ink-3">
        <p>
            <span class="font-semibold text-ink">Configure several displays at once.</span>
            Select displays with the checkboxes and apply a profile, a reusable set of settings such as check-in, booking, texts and branding.
            <a v-if="!profiles.length" :href="urls.profilesCreate" class="font-semibold text-navy hover:underline">Create your first profile</a>
            <a v-else :href="urls.profiles" class="font-semibold text-navy hover:underline">Manage profiles</a>.
        </p>
    </div>

    <div v-if="count" class="flex flex-col gap-2 rounded-[10px] border border-checkin-border bg-checkin-bg px-3.5 py-2.5 text-[13px]">
        <div class="flex flex-wrap items-center gap-3">
            <span class="font-semibold text-checkin-text">{{ count }} {{ noun }} selected</span>
            <template v-if="!profiles.length">
                <span class="text-ink-3">You need a profile to configure displays together.</span>
                <Button size="sm" :href="urls.profilesCreate" external>Create a profile</Button>
            </template>
            <template v-else>
                <label for="bulk-profile" class="sr-only">Profile</label>
                <select id="bulk-profile" v-model="profileId" class="rounded-md border border-line bg-surface px-2.5 py-1.5 text-xs text-ink focus:border-navy focus:outline-none">
                    <option value="">Select a profile</option>
                    <option v-for="p in profiles" :key="p.id" :value="p.id">{{ p.name }}</option>
                </select>
                <Button size="sm" :disabled="profileId === '' || sending" @click="submit(profileId)">Assign profile</Button>
                <Button size="sm" variant="secondary" :disabled="sending" @click="submit('')">Unlink</Button>
            </template>
            <div class="flex-1"></div>
            <button type="button" class="text-xs font-semibold text-checkin-text hover:underline" @click="clear">Clear selection</button>
        </div>
        <p v-if="error" class="text-xs font-semibold text-reserved-text" role="alert">{{ error }}</p>
        <p v-if="profiles.length && customised" class="text-xs text-checkin-text">
            {{ customised }} of the selected displays {{ customised === 1 ? 'has' : 'have' }} their own settings. Assigning a profile clears those so they follow the profile.
        </p>
    </div>
</template>
