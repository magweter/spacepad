@props(['show' => false])

<div
    x-data="{ show: false }"
    x-show="show"
    x-cloak
    @open-modal.window="if ($event.detail === 'manage-subscription') show = true"
    x-on:keydown.escape.window="show = false"
    class="relative z-50"
    role="dialog"
    aria-modal="true"
>
    {{-- Background backdrop --}}
    <div
        x-show="show"
        x-transition:enter="ease-out duration-300"
        x-transition:leave="ease-in duration-200"
        class="fixed inset-0 bg-gray-500 opacity-75 transition-opacity"
    ></div>

    <div class="fixed inset-0 z-50 overflow-y-auto">
        <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
            <div
                x-show="show"
                x-transition:enter="ease-out duration-300"
                x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                x-transition:leave="ease-in duration-200"
                x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                class="relative transform overflow-hidden rounded-lg bg-white px-4 pb-4 pt-5 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg sm:p-6"
            >
                <div>
                    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-blue-100">
                        <svg class="h-10 w-10 text-blue-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l4 2" />
                        </svg>
                    </div>
                    <div class="mt-3 text-center sm:mt-5">
                        <h3 class="text-base font-semibold leading-6 text-gray-900">Manage your subscription</h3>
                        <div class="mt-2">
                            <p class="text-sm text-gray-500 max-w-sm mx-auto">
                                You can manage your subscription in Lemon Squeezy using the order and payment emails you've received. In the email, you will find a big "Manage Subscription" button that works for both cloud-hosted and self-hosted subscriptions.
                            </p>
                            <p class="text-sm text-gray-500 max-w-sm mx-auto mt-2">
                                If you have a usage-based subscription (cloud hosted), your subscription automatically adapts every month based on your usage.
                            </p>
                            <p class="text-sm text-gray-500 max-w-sm mx-auto mt-2">
                                Can't find the email? Reach out to us at <a href="mailto:support@spacepad.io" class="text-blue-600 hover:text-blue-700">support@spacepad.io</a>.
                            </p>
                        </div>
                    </div>
                </div>
                <div class="mt-5 sm:mt-6">
                    <button
                        type="button"
                        @click="show = false"
                        class="inline-flex w-full justify-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50"
                    >
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>
</div> 