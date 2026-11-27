<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import Logo from '../../Components/Logo.vue';
import AuthShowcase from '../../Components/Auth/AuthShowcase.vue';
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

// Copy from design frames 09a (cloud) and 09b (self-hosted).
const title = computed(() => (isRegister.value ? 'Create a workspace' : 'Sign in'));
const panel = computed(() => (app.value.selfHosted
    ? {
        headline: 'Room displays, inside your own network.',
        lead: 'Your calendars, your database, your logs. Nothing leaves your network.',
        line: 'Runs on your own servers. Open source, per display, no user accounts needed.',
    }
    : {
        headline: 'Meeting room displays that just work. On any tablet.',
        lead: 'Connect a calendar, pair a tablet, done in four minutes.',
        line: isRegister.value
            ? 'Community covers one display for free. Pro starts at the second room, with 14 days to try it.'
            : 'Hosted in the EU. Per display, never per user. Open source.',
    }));
// Docs are for people running their own instance; cloud customers get the website instead.
const nav = computed(() => (app.value.selfHosted
    ? [
        { label: 'GitHub', href: props.urls.github, icon: 'github' },
        { label: 'Docs', href: props.urls.docs, icon: 'book' },
        { label: 'Help', href: props.urls.help, icon: 'help' },
    ]
    : [
        { label: 'Website', href: props.urls.website, icon: 'globe' },
        { label: 'Help', href: props.urls.help, icon: 'help' },
    ]));
// What sets Spacepad apart, under the panel and, on a phone where the panel is hidden, in the footer.
const usps = computed(() => (app.value.selfHosted
    ? ['Open source', 'Runs in your own network']
    : ['Open source', 'Hosted in the EU']));

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
    <Head :title="isRegister ? 'Create a workspace' : 'Sign in'" />

    <div class="flex min-h-screen flex-col bg-white text-sm leading-normal text-ink">
        <header class="flex h-20 flex-none items-center justify-between px-6 sm:px-10">
            <Logo :href="urls.website" />
            <nav aria-label="Spacepad" class="flex gap-5 sm:gap-7">
                <a v-for="n in nav" :key="n.label" :href="n.href" target="_blank" rel="noopener"
                   class="inline-flex items-center gap-[7px] text-[13px] font-medium text-ink-3 hover:text-navy">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <template v-if="n.icon === 'globe'"><circle cx="12" cy="12" r="10" /><path d="M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20" /><path d="M2 12h20" /></template>
                        <template v-else-if="n.icon === 'github'"><path d="M15 22v-4a4.8 4.8 0 0 0-1-3.5c3 0 6-2 6-5.5.08-1.25-.27-2.48-1-3.5.28-1.15.28-2.35 0-3.5 0 0-1 0-3 1.5-2.64-.5-5.36-.5-8 0C6 2 5 2 5 2c-.3 1.15-.3 2.35 0 3.5A5.403 5.403 0 0 0 4 9c0 3.5 3 5.5 6 5.5-.39.49-.68 1.05-.85 1.65-.17.6-.22 1.23-.15 1.85v4" /><path d="M9 18c-4.51 2-5-2-7-2" /></template>
                        <template v-else-if="n.icon === 'book'"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20" /><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z" /></template>
                        <template v-else><circle cx="12" cy="12" r="10" /><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3" /><path d="M12 17h.01" /></template>
                    </svg>
                    <span class="hidden sm:inline">{{ n.label }}</span>
                </a>
            </nav>
        </header>

        <div class="flex flex-1 flex-wrap gap-6 pb-4 lg:pr-8 lg:pb-8">
            <!-- Form side -->
            <main class="flex min-w-0 flex-[1_1_440px] flex-col px-6 pt-6 pb-2 sm:px-12 lg:pt-10 lg:pr-12 lg:pl-16">
                <div class="flex flex-1 flex-col items-center justify-center py-6">
                    <div class="flex w-full max-w-[380px] flex-col gap-8">
                        <FlashMessage :success="false" />

                        <!-- Sent state -->
                        <div v-if="sentTo" class="flex flex-col gap-6">
                            <span class="inline-flex h-12 w-12 items-center justify-center rounded-xl bg-amber-100 text-navy">
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="4" width="20" height="16" rx="2" /><path d="m22 7-10 7L2 7" /></svg>
                            </span>
                            <div class="flex flex-col gap-2">
                                <h1 class="text-[28px] font-bold tracking-tight">Check your inbox</h1>
                                <p class="text-muted">
                                    We sent a link to <strong class="font-semibold text-ink">{{ sentTo }}</strong>. Open it on this device to continue.
                                    It works once, for 15 minutes.
                                </p>
                            </div>
                            <p v-if="flash.success?.startsWith('Email resent')" class="text-[13px] text-ink-2">{{ flash.success }}</p>
                            <p class="text-[13px] text-muted">
                                Nothing yet? Check your spam folder,
                                <button type="button" class="font-semibold text-navy hover:underline disabled:opacity-50" :disabled="resending" @click="resend">send it again</button>
                                or <button type="button" class="font-semibold text-navy hover:underline" @click="useDifferentEmail">use a different email</button>.
                            </p>
                        </div>

                        <!-- Form -->
                        <div v-else class="flex flex-col gap-8">
                            <nav class="inline-flex self-start rounded-[10px] bg-line-soft p-1" aria-label="Sign in or create a workspace">
                                <Link :href="urls.login" class="rounded-lg px-4 py-[7px] text-[13px] font-semibold" :aria-current="!isRegister ? 'page' : null"
                                      :class="!isRegister ? 'bg-white text-navy shadow-[0_1px_2px_rgba(15,23,42,0.08)]' : 'text-muted hover:text-navy'">Sign in</Link>
                                <Link :href="urls.register" class="rounded-lg px-4 py-[7px] text-[13px] font-semibold" :aria-current="isRegister ? 'page' : null"
                                      :class="isRegister ? 'bg-white text-navy shadow-[0_1px_2px_rgba(15,23,42,0.08)]' : 'text-muted hover:text-navy'">Create workspace</Link>
                            </nav>

                            <h1 class="text-[28px] font-bold tracking-tight">{{ title }}</h1>

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
                                    <span class="h-px flex-1 bg-line"></span>or<span class="h-px flex-1 bg-line"></span>
                                </div>

                                <form class="flex flex-col gap-3" @submit.prevent="submit">
                                    <div class="flex flex-col gap-1.5">
                                        <label for="email" class="text-[13px] font-medium">Work email</label>
                                        <input id="email" v-model="form.email" type="email" required autocomplete="email" placeholder="you@company.com"
                                               class="rounded-[10px] border border-offline-border px-3.5 py-3 text-sm text-ink placeholder:text-offline focus:border-navy focus:outline-none"
                                               :class="form.errors.email ? 'border-[var(--status-reserved)]' : ''">
                                        <span v-if="form.errors.email" class="text-xs text-reserved-text">{{ form.errors.email }}</span>
                                        <span v-else-if="isRegister && domain" class="text-xs text-muted">
                                            We create a workspace for <strong class="font-semibold text-ink">{{ domain }}</strong>.
                                        </span>
                                    </div>
                                    <Button type="submit" size="lg" :disabled="form.processing">Continue</Button>
                                </form>
                            </template>

                            <p v-if="isRegister" class="text-xs text-offline">
                                By continuing you agree to the
                                <a :href="urls.terms" target="_blank" rel="noopener" class="font-medium text-muted hover:text-navy">Terms</a>,
                                <a :href="urls.privacy" target="_blank" rel="noopener" class="font-medium text-muted hover:text-navy">Privacy Policy</a>
                                <template v-if="!app.selfHosted"> and <a :href="urls.dpa" target="_blank" rel="noopener" class="font-medium text-muted hover:text-navy">Data Processing Agreement</a></template>.
                            </p>
                        </div>
                    </div>
                </div>

                <footer class="mt-auto flex flex-wrap gap-4 pt-8 text-xs text-muted">
                    <span>© Spacepad</span>
                    <a :href="urls.privacy" class="hover:text-navy">Privacy</a>
                    <a :href="urls.help" class="hover:text-navy">Help</a>
                    <span class="lg:hidden">{{ usps.join(', ') }}</span>
                </footer>
            </main>

            <!-- Brand side -->
            <AuthShowcase class="hidden flex-[0_1_716px] lg:flex" :headline="panel.headline" :lead="panel.lead" :line="panel.line" :usps="usps" />
        </div>
    </div>
</template>
