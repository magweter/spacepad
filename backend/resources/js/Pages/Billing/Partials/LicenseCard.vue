<script setup>
// A self-hosted instance gets Pro from a license key, validated against the license server.
import { useForm } from '@inertiajs/vue3';
import Button from '../../../DesignSystem/Button.vue';
import StatusBadge from '../../../DesignSystem/StatusBadge.vue';

const props = defineProps({ license: Object, canManage: Boolean, url: String, errors: Object });

const form = useForm({ license_key: '' });
const submit = () => form.post(props.url, { preserveScroll: true, preserveState: true, onSuccess: () => form.reset() });
</script>

<template>
    <div class="sp-card flex flex-col gap-2.5 border-dashed px-[18px] py-4">
        <div class="flex items-center justify-between gap-2">
            <h2 class="text-[13px] font-semibold">Self-hosted license</h2>
            <StatusBadge :status="license.active ? 'available' : 'offline'">{{ license.active ? 'Active' : 'Not active' }}</StatusBadge>
        </div>
        <p v-if="license.active" class="text-xs text-muted">
            This instance has Pro.<template v-if="license.expiresOn"> The license runs until {{ license.expiresOn }}.</template>
        </p>
        <p v-else class="text-xs text-muted">
            Running Spacepad in your own network? Buy a
            <a :href="license.buyUrl" target="_blank" rel="noopener" class="font-semibold text-navy hover:underline">Self-hosted Pro license</a>
            and enter the key from your purchase confirmation email.
        </p>
        <form v-if="canManage" class="flex flex-wrap gap-2" @submit.prevent="submit">
            <label for="license_key" class="sr-only">License key</label>
            <input id="license_key" v-model="form.license_key" type="text" required placeholder="XXXX-XXXX-XXXX-XXXX" autocomplete="off"
                   class="min-w-0 flex-[1_1_160px] rounded-lg border border-line px-3 py-2 font-mono text-[13px] focus:border-navy focus:outline-none">
            <Button type="submit" variant="secondary" :disabled="form.processing">{{ license.active ? 'Replace key' : 'Validate' }}</Button>
        </form>
        <p v-else class="text-xs text-muted">Only owners and admins can enter a license key.</p>
        <span v-if="form.errors.license_key || errors?.license_key" class="text-xs text-reserved-text">{{ form.errors.license_key || errors.license_key }}</span>
    </div>
</template>
