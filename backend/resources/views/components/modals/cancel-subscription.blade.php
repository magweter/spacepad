@props(['endsAt' => null])

{{-- Cancelling from the app asks one thing first: why. The subscription runs until the end
     of the paid period, which the modal says before anything is final. --}}
<div
    x-data="{ show: @js($errors->has('reason') || $errors->has('details')), reason: @js(old('reason')) }"
    x-show="show"
    x-cloak
    @open-modal.window="if ($event.detail === 'cancel-subscription') show = true"
    x-on:keydown.escape.window="show = false"
    class="relative z-50"
    role="dialog"
    aria-modal="true"
>
    <div x-show="show" x-transition:enter="ease-out duration-300" x-transition:leave="ease-in duration-200"
        class="fixed inset-0 bg-gray-500 opacity-75 transition-opacity"></div>

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
                <form action="{{ route('billing.cancel') }}" method="POST">
                    @csrf
                    <h3 class="text-base font-semibold leading-6 text-gray-900">Cancel subscription</h3>
                    <p class="mt-2 text-sm text-gray-500">
                        Before you go: why are you cancelling? One answer is enough, and it helps us make Spacepad better.
                    </p>

                    <div class="mt-4">
                        <x-billing.cancellation-reasons />
                        @error('reason') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                        @error('details') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <p class="mt-4 text-sm text-gray-500">
                        @if($endsAt)
                            Pro stays active until {{ $endsAt->format('j F Y') }}.
                        @else
                            Pro stays active until the end of the current period.
                        @endif
                        After that the workspace moves to the free plan, with one active display.
                    </p>

                    <div class="mt-5 sm:mt-6 sm:flex sm:flex-row-reverse sm:gap-3">
                        <button type="submit"
                            class="inline-flex w-full justify-center rounded-md bg-red-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-red-500 sm:w-auto">
                            Cancel subscription
                        </button>
                        <button type="button" @click="show = false"
                            class="mt-3 inline-flex w-full justify-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 sm:mt-0 sm:w-auto">
                            Keep subscription
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
