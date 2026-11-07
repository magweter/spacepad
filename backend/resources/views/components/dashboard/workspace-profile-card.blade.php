@props(['workspace'])

@php
    $rooms = \App\Enums\RoomsEstimate::cases();
    $solutions = \App\Enums\PreviousSolution::cases();
    $firstDisplay = $workspace->displays()->count() === 1;
@endphp

{{-- Two optional questions, shown after the first display so they never stand between signing
     up and a working display. Each click is saved straight away; the card goes once the last
     question is answered or someone closes it. --}}
<div
    x-data="{
        rooms: @js($workspace->latestAnswer(\App\Enums\OnboardingQuestion::ROOMS)?->value),
        solution: null,
        other: '',
        otherError: '',
        showOther: false,
        done: false,
        hidden: false,
        csrf: @js(csrf_token()),
        async save(payload) {
            await fetch(@js(route('workspace.profile.update')), {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': this.csrf,
                },
                body: JSON.stringify(payload),
            }).catch(() => {});
        },
        pickRooms(value) {
            this.rooms = value;
            this.save({ question: 'rooms', value: value });
        },
        pickSolution(value) {
            this.solution = value;
            if (value === 'other') {
                this.showOther = true;
                this.$nextTick(() => this.$refs.other.focus());
                return;
            }
            this.save({ question: 'previous_solution', value: value });
            this.finish();
        },
        saveOther() {
            if (this.other.trim() === '') {
                this.otherError = 'Enter a name first';
                return;
            }
            this.save({ question: 'previous_solution', value: 'other', text: this.other.trim() });
            this.finish();
        },
        finish() {
            this.done = true;
            setTimeout(() => this.hidden = true, 2500);
        },
        dismiss() {
            this.hidden = true;
            fetch(@js(route('workspace.profile.dismiss')), {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': this.csrf },
            }).catch(() => {});
        },
    }"
    x-show="! hidden"
    class="mb-4 rounded-xl bg-indigo-50 border border-indigo-200 p-4 flex items-start gap-4"
>
    <div class="flex-shrink-0 mt-1">
        <span class="inline-flex items-center justify-center h-10 w-10 rounded-full bg-indigo-100">
            <x-icons.information class="h-6 w-6 text-indigo-500" />
        </span>
    </div>

    <div class="flex-1 min-w-0">
        <template x-if="done">
            <div>
                <h3 class="text-md font-semibold text-indigo-900 mb-1">Thanks, that helps.</h3>
                <p class="text-sm text-indigo-800">This card won't show again.</p>
            </div>
        </template>

        <div x-show="! done">
            <div class="flex items-start justify-between gap-4">
                <h3 class="text-md font-semibold text-indigo-900 mb-1">
                    {{ $firstDisplay ? 'Your first display is live. Two quick questions?' : 'Two quick questions?' }}
                </h3>
                <button type="button" @click="dismiss()" class="text-indigo-400 hover:text-indigo-600" aria-label="Dismiss">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <p class="text-sm text-indigo-800 mb-3">It helps us set you up the right way. One click each, skip whenever you like.</p>

            <p class="text-sm font-medium text-indigo-900 mb-2">How many rooms do you want to show?</p>
            <div class="flex flex-wrap gap-2 mb-4">
                @foreach($rooms as $option)
                    <button type="button" @click="pickRooms(@js($option->value))"
                        :class="rooms === @js($option->value) ? 'bg-indigo-600 text-white ring-indigo-600' : 'bg-white text-gray-900 ring-gray-300 hover:bg-gray-50'"
                        class="rounded-md px-3 py-1.5 text-sm font-semibold shadow-xs ring-1 ring-inset">
                        {{ $option->label() }}
                    </button>
                @endforeach
            </div>

            <div :class="rooms ? '' : 'opacity-50 pointer-events-none'">
                <p class="text-sm font-medium text-indigo-900 mb-2">What did you use before Spacepad?</p>
                <div class="flex flex-wrap gap-2">
                    @foreach($solutions as $option)
                        <button type="button" @click="pickSolution(@js($option->value))"
                            :class="solution === @js($option->value) ? 'bg-indigo-600 text-white ring-indigo-600' : 'bg-white text-gray-900 ring-gray-300 hover:bg-gray-50'"
                            class="rounded-md px-3 py-1.5 text-sm font-semibold shadow-xs ring-1 ring-inset">
                            {{ $option->label() }}
                        </button>
                    @endforeach
                </div>

                <div x-show="showOther" x-cloak class="mt-3 flex gap-2 max-w-md">
                    <input type="text" x-ref="other" x-model="other" @input="otherError = ''" @keydown.enter.prevent="saveOther()"
                        maxlength="255" placeholder="Name of the product"
                        class="block w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                    <button type="button" @click="saveOther()"
                        class="inline-flex items-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-xs hover:bg-indigo-700">
                        Save
                    </button>
                </div>
                <p x-show="otherError" x-text="otherError" class="mt-1 text-sm text-red-600"></p>
            </div>
        </div>
    </div>
</div>
