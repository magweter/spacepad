@props(['cancellation'])

{{-- A cancellation made outside the app (the billing portal or a Lemon Squeezy email) still
     gets its one question, the next time an owner or admin opens the dashboard. --}}
<div x-data="{ reason: @js(old('reason')) }" class="mb-4 rounded-xl bg-gray-50 border border-gray-200 p-4">
    <div class="flex items-start justify-between gap-4">
        <div>
            <h3 class="text-md font-semibold text-gray-900 mb-1">Sorry to see you go</h3>
            <p class="text-sm text-gray-600 mb-3">Your subscription is cancelled. Would you tell us why? One answer is enough.</p>
        </div>
        <form action="{{ route('billing.cancellations.dismiss', $cancellation) }}" method="POST">
            @csrf
            <button type="submit" class="text-gray-400 hover:text-gray-600" aria-label="Dismiss">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
            </button>
        </form>
    </div>

    <form action="{{ route('billing.cancellations.feedback', $cancellation) }}" method="POST" class="max-w-md">
        @csrf
        <x-billing.cancellation-reasons />
        @error('reason') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
        @error('details') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
        <button type="submit" class="mt-3 inline-flex items-center rounded-md bg-oxford px-3 py-2 text-sm font-semibold text-white shadow-sm hover:opacity-90">
            Send
        </button>
    </form>
</div>
