@php $consentUrl = session('admin_consent_url', '') @endphp

<div x-data="{
        show: {{ session('needs_admin_consent') ? 'true' : 'false' }},
        copied: false,
        consentUrl: @js($consentUrl),
        copy() {
            navigator.clipboard.writeText(this.consentUrl);
            this.copied = true;
            setTimeout(() => this.copied = false, 2000);
        }
    }"
    x-show="show"
    x-cloak
    x-on:keydown.escape.window="show = false"
    class="relative z-50"
    role="dialog"
    aria-modal="true">

    {{-- Backdrop --}}
    <div x-show="show"
        x-transition:enter="ease-out duration-300"
        x-transition:leave="ease-in duration-200"
        class="fixed inset-0 bg-gray-500 opacity-75 transition-opacity"
        @click="show = false">
    </div>

    <div class="fixed inset-0 z-50 overflow-y-auto">
        <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
            <div x-show="show"
                x-transition:enter="ease-out duration-300"
                x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                x-transition:leave="ease-in duration-200"
                x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                class="relative transform overflow-hidden rounded-lg bg-white px-4 pb-4 pt-5 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg sm:p-6"
                @click.away="show = false">

                {{-- Icon --}}
                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-yellow-100">
                    <svg class="h-6 w-6 text-yellow-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25z" />
                    </svg>
                </div>

                <div class="mt-4 text-center">
                    <h3 class="text-base font-semibold leading-6 text-gray-900">
                        Your IT admin needs to approve Spacepad
                    </h3>
                    <p class="mt-2 text-sm text-gray-600">
                        Your Microsoft 365 organisation requires an administrator to approve new apps before you can connect them. This is a one-time step — once your admin approves, everyone in your organisation can connect Spacepad.
                    </p>
                </div>

                {{-- Steps --}}
                <div class="mt-5 space-y-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">What to do</p>

                    <div class="flex gap-3">
                        <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-blue-600 text-xs font-semibold text-white">1</span>
                        <p class="text-sm text-gray-700">Copy the link below and send it to your IT administrator.</p>
                    </div>
                    <div class="flex gap-3">
                        <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-blue-600 text-xs font-semibold text-white">2</span>
                        <p class="text-sm text-gray-700">Your admin opens the link, signs in, and clicks <strong>Accept</strong>.</p>
                    </div>
                    <div class="flex gap-3">
                        <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-blue-600 text-xs font-semibold text-white">3</span>
                        <p class="text-sm text-gray-700">Come back here and connect your Microsoft account — it will work straight away.</p>
                    </div>
                </div>

                {{-- Consent URL --}}
                <div class="mt-5">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">Approval link for your admin</p>
                    <div class="flex items-center gap-2 rounded-md bg-gray-50 ring-1 ring-gray-200 p-3">
                        <p class="flex-1 truncate text-xs text-gray-700 font-mono">{{ $consentUrl }}</p>
                        <button type="button"
                            @click="copy()"
                            class="shrink-0 rounded-md bg-white px-2.5 py-1.5 text-xs font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                            <span x-show="!copied">Copy</span>
                            <span x-show="copied" class="text-green-600">Copied!</span>
                        </button>
                    </div>
                </div>

                {{-- Email template --}}
                <details class="mt-4 group">
                    <summary class="cursor-pointer text-sm text-blue-600 hover:text-blue-500 select-none">
                        Need a template email to send to your admin?
                    </summary>
                    <div class="mt-2 rounded-md bg-gray-50 ring-1 ring-gray-200 p-3 text-xs text-gray-700 whitespace-pre-wrap leading-relaxed">Hi,

I'm trying to connect Spacepad (a room display app) to our Microsoft 365 calendar. Our organisation requires admin approval before new apps can be authorised.

Could you please open the link below and click Accept? It's a one-time approval that lets anyone in our organisation connect their account.

{{ $consentUrl }}

Thanks!</div>
                </details>

                <div class="mt-6 flex justify-end gap-3">
                    <button type="button"
                        @click="show = false"
                        class="rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                        Close
                    </button>
                    <button type="button"
                        @click="show = false; $dispatch('open-permission-modal', { provider: 'outlook' })"
                        class="rounded-md bg-blue-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-500">
                        Try connecting again
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
