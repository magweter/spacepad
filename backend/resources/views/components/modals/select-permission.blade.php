@props(['provider' => 'outlook'])

<div x-data="{ 
        show: false,
        provider: '{{ $provider }}',
        permissionType: 'write',
        loading: false
    }" x-show="show" x-cloak
    @open-permission-modal.window="if ($event.detail.provider === provider) { show = true; permissionType = 'write'; }"
    x-on:keydown.escape.window="show = false" class="relative z-50" role="dialog" aria-modal="true">
    {{-- Background backdrop --}}
    <div x-show="show" x-transition:enter="ease-out duration-300" x-transition:leave="ease-in duration-200"
        class="fixed inset-0 bg-gray-500 opacity-75 transition-opacity" @click="show = false"></div>

    <div class="fixed inset-0 z-50 overflow-y-auto">
        <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
            <div x-show="show" x-transition:enter="ease-out duration-300"
                x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                x-transition:leave="ease-in duration-200"
                x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                class="relative transform overflow-hidden rounded-lg bg-white px-4 pb-4 pt-5 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg sm:p-6"
                @click.away="show = false">
                <div>
                    <div class="mt-3 text-center sm:mt-5">
                        <h3 class="text-base font-semibold leading-6 text-gray-900">Select account permissions</h3>
                        <p class="mt-2 text-sm text-gray-700">Choose the level of access you want to grant to your
                            {{ ucfirst($provider) }} account.
                        </p>
                    </div>

                    <form x-ref="permissionForm"
                        :action="provider === 'outlook' ? '{{ route('outlook-accounts.auth') }}' : (provider === 'google' ? '{{ route('google-accounts.auth') }}' : '#')"
                        method="POST" @submit.prevent="loading = true; $refs.permissionForm.submit()" class="mt-6">
                        @csrf
                        <div class="space-y-4">
                            <!-- Write Permission Option -->
                            <label
                                class="relative flex cursor-pointer rounded-lg border p-4 shadow-sm focus:outline-none transition-all"
                                :class="permissionType === 'write' ? 'border-blue-600 bg-blue-50' : 'border-gray-300 bg-white hover:border-gray-400'">
                                <input type="radio" name="permission_type" value="write" class="sr-only"
                                    x-model="permissionType">
                                <span class="flex flex-1">
                                    <span class="flex flex-col">
                                        <span class="block text-sm font-medium"
                                            :class="permissionType === 'write' ? 'text-blue-900' : 'text-gray-900'">
                                            Read & Write
                                            <span
                                                class="ml-2 inline-flex items-center rounded-md bg-blue-50 px-2 py-1 text-xs font-medium text-blue-700 ring-1 ring-inset ring-blue-600/20">Recommended</span>
                                        </span>
                                        <span class="mt-1 text-sm"
                                            :class="permissionType === 'write' ? 'text-blue-700' : 'text-gray-500'">
                                            View calendar events and create new bookings. Allows events booked from the tablet display to be written to the calendar.
                                        </span>
                                    </span>
                                </span>
                                <svg class="h-5 w-5 flex-shrink-0"
                                    :class="permissionType === 'write' ? 'text-blue-600' : 'text-gray-400'"
                                    viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd"
                                        d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a1 1 0 00-1.714-1.382L9 9.586 7.857 8.809a1 1 0 00-1.714 1.382l2 2.5a1 1 0 001.428 0l4-5z"
                                        clip-rule="evenodd" />
                                </svg>
                                <span class="pointer-events-none absolute -inset-px rounded-lg border-2"
                                    aria-hidden="true"
                                    :class="permissionType === 'write' ? 'border-blue-600' : 'border-transparent'"></span>
                            </label>

                            <!-- Read Permission Option -->
                            <label
                                class="relative flex cursor-pointer rounded-lg border p-4 shadow-sm focus:outline-none transition-all"
                                :class="permissionType === 'read' ? 'border-blue-600 bg-blue-50' : 'border-gray-300 bg-white hover:border-gray-400'">
                                <input type="radio" name="permission_type" value="read" class="sr-only"
                                    x-model="permissionType">
                                <span class="flex flex-1">
                                    <span class="flex flex-col">
                                        <span class="block text-sm font-medium"
                                            :class="permissionType === 'read' ? 'text-blue-900' : 'text-gray-900">Read
                                            Only</span>
                                        <span class="mt-1 text-sm"
                                            :class="permissionType === 'read' ? 'text-blue-700' : 'text-gray-500'">
                                            View calendar events and room availability. Cannot create or modify
                                            events in your calendar.<br><br>
                                            <strong class="font-semibold"
                                                :class="permissionType === 'read' ? 'text-blue-900' : 'text-gray-900'">When
                                                you choose this option, ad-hoc room bookings will not appear in your
                                                calendar.</strong>
                                        </span>
                                    </span>
                                </span>
                                <svg class="h-5 w-5 flex-shrink-0"
                                    :class="permissionType === 'read' ? 'text-blue-600' : 'text-gray-400'"
                                    viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd"
                                        d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a1 1 0 00-1.714-1.382L9 9.586 7.857 8.809a1 1 0 00-1.714 1.382l2 2.5a1 1 0 001.428 0l4-5z"
                                        clip-rule="evenodd" />
                                </svg>
                                <span class="pointer-events-none absolute -inset-px rounded-lg border-2"
                                    aria-hidden="true"
                                    :class="permissionType === 'read' ? 'border-blue-600' : 'border-transparent'"></span>
                            </label>
                        </div>

                        <div class="mt-6 flex items-center justify-end gap-x-3">
                            <button type="button" @click="show = false" :disabled="loading"
                                class="text-sm font-semibold leading-6 text-gray-900 disabled:opacity-50">
                                Cancel
                            </button>
                            <button type="submit" :disabled="loading"
                                class="rounded-md bg-blue-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600 disabled:opacity-50 disabled:cursor-not-allowed">
                                <span x-show="!loading">Continue to {{ ucfirst($provider) }}</span>
                                <span x-show="loading">Loading...</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>