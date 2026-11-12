<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import Logo from '../../Components/Logo.vue';
import DevicePreview from '../../Components/DevicePreview.vue';
import FlashMessage from '../../Components/FlashMessage.vue';
import Button from '../../DesignSystem/Button.vue';
import { useRecaptcha } from '../../Composables/useRecaptcha';

const props = defineProps({
    mode: { type: String, default: 'signin' },
    sentTo: { type: String, default: null },
    urls: { type: Object, required: true },
});

const page = usePage();
const app = computed(() => page.props.app);
const flash = computed(() => page.props.flash || {});
const isRegister = computed(() => props.mode === 'register');
const recaptcha = useRecaptcha(app.value.recaptchaSiteKey);

const form = useForm({ email: '', 'g-recaptcha-response': null });
const resending = ref(false);

const domain = computed(() => {
    const at = form.email.indexOf('@');
    return at > 0 && form.email.length > at + 1 ? form.email.slice(at + 1).toLowerCase() : null;
});

const copy = computed(() => (isRegister.value
    ? {
        title: 'Create your workspace',
        subtitle: 'Start with one free display. No credit card, no password.',
        cta: 'Create account with email',
    }
    : {
        title: 'Welcome back',
        subtitle: 'Sign in to manage your displays and boards.',
        cta: 'Send me a sign-in link',
    }));

async function submit() {
    form['g-recaptcha-response'] = await recaptcha(isRegister.value ? 'register' : 'login');
    form.post(isRegister.value ? props.urls.registerStore : props.urls.loginStore, {
        preserveScroll: true,
    });
}

async function resend() {
    resending.value = true;

    if (isRegister.value) {
        router.post(props.urls.registerResend, {}, { onFinish: () => (resending.value = false) });
        return;
    }

    form.email = props.sentTo;
    form['g-recaptcha-response'] = await recaptcha('login');
    form.post(props.urls.loginStore, { onFinish: () => (resending.value = false) });
}

function useDifferentEmail() {
    router.visit(isRegister.value ? props.urls.register : props.urls.login, { preserveState: false });
}
</script>

<template>
    <Head :title="isRegister ? 'Create account' : 'Sign in'" />

    <div class="flex min-h-screen flex-wrap bg-white text-sm leading-normal text-ink">
        <!-- Form side -->
        <main class="flex min-w-0 flex-[1_1_480px] flex-col px-6 py-10 sm:px-12">
            <Logo :href="urls.website" class="self-start" />

            <div class="flex flex-1 flex-col items-center justify-center py-10">
                <div class="flex w-full max-w-[400px] flex-col gap-7">
                    <!-- Mode tabs -->
                    <nav class="inline-flex self-start rounded-[10px] bg-line-soft p-1" aria-label="Sign in or create an account">
                        <Link :href="urls.login" class="rounded-lg px-4 py-1.5 text-[13px] font-semibold"
                              :class="!isRegister ? 'bg-white text-navy shadow-[0_1px_2px_rgba(15,23,42,0.08)]' : 'text-muted hover:text-navy'">Sign in</Link>
                        <Link :href="urls.register" class="rounded-lg px-4 py-1.5 text-[13px] font-semibold"
                              :class="isRegister ? 'bg-white text-navy shadow-[0_1px_2px_rgba(15,23,42,0.08)]' : 'text-muted hover:text-navy'">Create account</Link>
                    </nav>

                    <FlashMessage />

                    <!-- Sent state -->
                    <div v-if="sentTo" class="flex flex-col gap-5">
                        <span class="inline-flex h-14 w-14 items-center justify-center rounded-[14px] bg-amber-100 text-checkin-text">
                            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="4" width="20" height="16" rx="2" /><path d="m22 7-10 7L2 7" /></svg>
                        </span>
                        <div class="flex flex-col gap-1.5">
                            <h1 class="text-[28px] font-bold tracking-tight">Check your inbox</h1>
                            <p class="text-muted">
                                We sent a sign-in link to <strong class="font-semibold text-ink">{{ sentTo }}</strong>.
                                It works once, for 15 minutes, on this device.
                            </p>
                        </div>
                        <div class="flex flex-col gap-1 rounded-xl border border-line bg-surface-raised p-4">
                            <span class="flex items-center gap-2.5 text-[13px]"><span class="h-2 w-2 rounded-full bg-checkin"></span>Nothing in your inbox?</span>
                            <span class="text-xs text-muted">Check your spam folder, or send the link again.</span>
                        </div>
                        <p v-if="flash.success?.startsWith('Email resent')" class="text-[13px] text-available-text">{{ flash.success }}</p>
                        <div class="flex flex-wrap gap-4 text-[13px]">
                            <button type="button" class="font-semibold text-navy disabled:opacity-50" :disabled="resending" @click="resend">Send the link again</button>
                            <button type="button" class="font-semibold text-muted hover:text-navy" @click="useDifferentEmail">Use a different email</button>
                        </div>
                    </div>

                    <!-- Form -->
                    <div v-else class="flex flex-col gap-7">
                        <div class="flex flex-col gap-1.5">
                            <h1 class="text-[28px] font-bold tracking-tight">{{ copy.title }}</h1>
                            <p class="text-muted">{{ copy.subtitle }}</p>
                        </div>

                        <div v-if="app.providers.microsoft || app.providers.google" class="flex flex-col gap-2.5">
                            <a v-if="app.providers.microsoft" :href="urls.microsoft"
                               class="flex items-center justify-center gap-2.5 rounded-[10px] border border-line bg-white px-4 py-3 text-sm font-semibold text-ink hover:border-navy">
                                <span class="grid h-5 w-5 grid-cols-2 gap-0.5" aria-hidden="true"><span class="bg-[#F25022]"></span><span class="bg-[#7FBA00]"></span><span class="bg-[#00A4EF]"></span><span class="bg-[#FFB900]"></span></span>
                                Continue with Microsoft
                            </a>
                            <a v-if="app.providers.google" :href="urls.google"
                               class="flex items-center justify-center gap-2.5 rounded-[10px] border border-line bg-white px-4 py-3 text-sm font-semibold text-ink hover:border-navy">
                                <span class="inline-block h-5 w-5 rounded-full border-[3px] border-[#4285F4] border-r-[#34A853] border-b-[#FBBC05] border-l-[#EA4335]" aria-hidden="true"></span>
                                Continue with Google
                            </a>
                        </div>

                        <template v-if="app.emailLogin">
                            <div v-if="app.providers.microsoft || app.providers.google" class="flex items-center gap-3 text-xs text-offline">
                                <span class="h-px flex-1 bg-line"></span>or use your email<span class="h-px flex-1 bg-line"></span>
                            </div>

                            <form class="flex flex-col gap-3.5" @submit.prevent="submit">
                                <div class="flex flex-col gap-1.5">
                                    <label for="email" class="text-[13px] font-medium">Work email</label>
                                    <input id="email" v-model="form.email" type="email" required autocomplete="email" placeholder="you@company.com"
                                           class="rounded-[10px] border border-offline-border px-3.5 py-3 text-sm text-ink placeholder:text-offline focus:border-navy focus:outline-none"
                                           :class="form.errors.email ? 'border-red-400' : ''">
                                    <span v-if="form.errors.email" class="text-xs text-reserved-text">{{ form.errors.email }}</span>
                                    <span v-else-if="isRegister && domain" class="text-xs text-muted">
                                        We create a workspace for <strong class="font-semibold text-ink">{{ domain }}</strong>.
                                    </span>
                                </div>
                                <Button type="submit" size="lg" :disabled="form.processing">{{ copy.cta }}</Button>
                                <span class="text-center text-xs text-muted">No password needed. We email you a link that signs you in on this device.</span>
                            </form>
                        </template>

                        <p v-if="isRegister" class="text-center text-xs text-muted">
                            By creating an account you agree to the
                            <a :href="urls.terms" target="_blank" rel="noopener" class="font-semibold text-navy">Terms of Service</a>,
                            <a :href="urls.privacy" target="_blank" rel="noopener" class="font-semibold text-navy">Privacy Policy</a>
                            <template v-if="!app.selfHosted"> and <a :href="urls.dpa" target="_blank" rel="noopener" class="font-semibold text-navy">Data Processing Agreement</a></template>.
                        </p>
                    </div>
                </div>
            </div>

            <footer class="flex flex-wrap gap-4 text-xs text-muted">
                <span>© Spacepad</span>
                <a :href="urls.privacy" class="hover:text-navy">Privacy</a>
                <a :href="urls.website" class="hover:text-navy">spacepad.io</a>
            </footer>
        </main>

        <!-- Brand side -->
        <aside aria-hidden="true" class="relative hidden min-w-0 flex-[1_1_520px] flex-col justify-center gap-10 overflow-hidden bg-navy px-16 py-18 text-white lg:flex">
            <div class="absolute -top-30 -right-30 h-[420px] w-[420px] rounded-full bg-amber opacity-[0.12]"></div>
            <div class="flex max-w-[480px] flex-col gap-3">
                <span class="text-xs font-semibold tracking-[0.08em] text-amber uppercase">Room displays, done simply</span>
                <h2 class="text-4xl leading-[1.15] font-bold tracking-tight">Your first room display is live in four minutes.</h2>
                <p class="text-[15px] text-on-navy-2">
                    Connect Microsoft 365, Google or CalDAV, pick a room, pair a tablet. Self-host it if you'd rather keep everything in your own network.
                </p>
            </div>
            <DevicePreview class="max-w-[560px]" />
            <div class="flex flex-wrap gap-7 text-[13px] text-on-navy-2">
                <span class="flex flex-col"><strong class="text-lg font-bold text-white">Open source</strong>Auditable, self-hostable</span>
                <span class="flex flex-col"><strong class="text-lg font-bold text-white">Privacy-first</strong>Hide titles, EU hosting</span>
            </div>
        </aside>
    </div>
</template>
