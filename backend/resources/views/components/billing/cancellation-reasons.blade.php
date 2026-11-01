{{-- The reason list shared by the cancel modal and the dashboard card. Needs an Alpine scope
     with `reason`; the follow-up field shows for reasons that ask for a name. --}}
@php $reasons = \App\Enums\CancellationReason::cases(); @endphp

<fieldset>
    <legend class="sr-only">Why are you cancelling?</legend>
    <div class="space-y-2">
        @foreach($reasons as $option)
            <label class="flex items-center gap-3 rounded-md border border-gray-200 px-3 py-2 text-sm text-gray-900 cursor-pointer hover:bg-gray-50"
                :class="reason === @js($option->value) ? 'border-blue-500 bg-blue-50' : ''">
                <input type="radio" name="reason" value="{{ $option->value }}" x-model="reason"
                    class="h-4 w-4 border-gray-300 text-blue-600 focus:ring-blue-600" required>
                {{ $option->label() }}
            </label>
        @endforeach
    </div>
</fieldset>

@foreach($reasons as $option)
    @if($option->detailsPrompt())
        <div x-show="reason === @js($option->value)" x-cloak class="mt-3">
            <label class="block text-sm font-medium text-gray-900 mb-1">{{ $option->detailsPrompt() }}</label>
            <textarea name="details" rows="2" maxlength="2000"
                :disabled="reason !== @js($option->value)"
                @if($option === \App\Enums\CancellationReason::OTHER) :required="reason === 'other'" @endif
                class="block w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">{{ old('details') }}</textarea>
        </div>
    @endif
@endforeach
