<script setup>
// Invite a colleague by email. Ownership is never handed out by invitation.
import { useForm } from '@inertiajs/vue3';
import Button from '../../../DesignSystem/Button.vue';

const props = defineProps({ url: String });

const form = useForm({ email: '', role: 'member' });
const submit = () => form.post(props.url, { preserveScroll: true, preserveState: true, onSuccess: () => form.reset('email') });
</script>

<template>
    <form class="sp-card flex flex-row flex-wrap items-center gap-2.5 px-[18px] py-4" @submit.prevent="submit">
        <label for="invite_email" class="sr-only">Email address</label>
        <input id="invite_email" v-model="form.email" type="email" required placeholder="colleague@company.com"
               class="min-w-0 flex-[1_1_220px] rounded-lg border border-line px-3 py-2 text-[13px] focus:border-navy focus:outline-none">
        <label for="invite_role" class="sr-only">Role</label>
        <select id="invite_role" v-model="form.role" class="rounded-lg border border-line bg-surface px-3 py-2 text-[13px] focus:border-navy focus:outline-none">
            <option value="member">Member, manages displays and boards</option>
            <option value="admin">Admin, also invites colleagues and pays</option>
        </select>
        <Button type="submit" :disabled="form.processing">Send invite</Button>
        <span v-if="form.errors.email || form.errors.role" class="basis-full text-xs text-reserved-text">{{ form.errors.email || form.errors.role }}</span>
        <span class="basis-full text-xs text-muted">They get an email with a link that signs them in and adds them to this workspace. There is no charge per team member.</span>
    </form>
</template>
