@props(['displays' => collect()])

<script>
window.__diagRunUrls   = @json($displays->mapWithKeys(fn($d) => [$d->id => route('displays.diagnostics.run', $d)]));
window.__diagDispNames = @json($displays->mapWithKeys(fn($d) => [$d->id => $d->name]));
</script>

<div
    x-data="{
        show: false,
        selectedId: null,
        running: false,
        steps: [],
        ran: false,
        elapsed: null,
        fetchErr: null,

        runUrls: window.__diagRunUrls ?? {},
        displayNames: window.__diagDispNames ?? {},

        get runUrl() { return this.runUrls[this.selectedId] ?? null; },
        get displayName() { return this.displayNames[this.selectedId] ?? ''; },

        open(displayId) {
            this.selectedId = displayId ?? Object.keys(this.runUrls)[0] ?? null;
            this.steps = [];
            this.ran = false;
            this.elapsed = null;
            this.fetchErr = null;
            this.show = true;
        },

        reset() {
            this.steps = [];
            this.ran = false;
            this.elapsed = null;
            this.fetchErr = null;
        },

        async run() {
            if (!this.runUrl || this.running) return;
            this.reset();
            this.running = true;
            const t0 = Date.now();
            try {
                const resp = await fetch(this.runUrl, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                });
                if (!resp.ok) throw new Error('HTTP ' + resp.status + ': ' + resp.statusText);
                const json = await resp.json();
                this.steps = json.steps ?? [];
                this.elapsed = ((Date.now() - t0) / 1000).toFixed(1);
                this.ran = true;
                this.$nextTick(() => {
                    const bad = this.steps.find(s => s.status !== 'ok');
                    if (bad) {
                        const el = document.getElementById('diag-details-' + bad.number);
                        if (el) el.classList.remove('hidden');
                        const ch = document.getElementById('diag-chevron-' + bad.number);
                        if (ch) ch.style.transform = 'rotate(180deg)';
                    }
                });
            } catch (err) {
                this.fetchErr = err.message;
            } finally {
                this.running = false;
            }
        }
    }"
    x-on:open-diagnostics.window="open($event.detail?.displayId ?? null)"
    x-on:keydown.escape.window="show = false"
    x-show="show"
    x-cloak
    class="relative z-50"
    role="dialog"
    aria-modal="true">

    {{-- Backdrop --}}
    <div x-show="show"
         x-transition:enter="ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-75"
         x-transition:leave="ease-in duration-150"
         x-transition:leave-start="opacity-75"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 bg-gray-500 opacity-75"
         @click="show = false">
    </div>

    {{-- Panel --}}
    <div class="fixed inset-0 z-50 overflow-y-auto">
        <div class="flex min-h-full items-end justify-center p-4 sm:items-center sm:p-0">
            <div x-show="show"
                 x-transition:enter="ease-out duration-200"
                 x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                 x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave="ease-in duration-150"
                 x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                 class="relative w-full transform rounded-xl bg-white shadow-2xl transition-all sm:my-8 sm:max-w-2xl"
                 @click.stop>

                {{-- Header --}}
                <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4">
                    <div>
                        <h2 class="text-base font-semibold text-gray-900">Calendar sync diagnostics</h2>
                        <p class="mt-0.5 text-xs text-gray-500">Trace the full event pipeline and find exactly where events disappear.</p>
                    </div>
                    <button type="button" @click="show = false"
                            class="rounded-md p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition-colors">
                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z" />
                        </svg>
                    </button>
                </div>

                {{-- Body --}}
                <div class="px-6 py-5 space-y-5 max-h-[70vh] overflow-y-auto">

                    @if($displays->isEmpty())
                        <div class="rounded-lg bg-amber-50 border border-amber-200 p-4 text-sm text-amber-800">
                            No displays found for this workspace.
                        </div>
                    @else
                        {{-- Display selector + run button --}}
                        <div class="flex gap-3 items-end">
                            <div class="flex-1">
                                <label class="block text-xs font-medium text-gray-600 mb-1">Display</label>
                                <select x-model="selectedId" @change="reset()"
                                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                                    @foreach($displays as $d)
                                        <option value="{{ $d->id }}">
                                            {{ $d->name }}
                                            @if($d->calendar)
                                                -
                                                @if($d->calendar->outlook_account_id) Microsoft 365
                                                @elseif($d->calendar->google_account_id) Google Calendar
                                                @elseif($d->calendar->caldav_account_id) CalDAV
                                                @endif
                                                @if($d->calendar->room) (room) @endif
                                            @else
                                                - no calendar
                                            @endif
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <button type="button" @click="run()" :disabled="running || !selectedId"
                                    class="inline-flex items-center gap-2 rounded-lg bg-gray-900 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-gray-700 disabled:opacity-50 transition-colors whitespace-nowrap">
                                <svg x-show="!running" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M2 10a8 8 0 1116 0A8 8 0 012 10zm6.39-2.908a.75.75 0 01.766.027l3.5 2.25a.75.75 0 010 1.262l-3.5 2.25A.75.75 0 018 12.25v-4.5a.75.75 0 01.39-.658z" clip-rule="evenodd"/>
                                </svg>
                                <svg x-show="running" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
                                </svg>
                                <span x-text="running ? 'Running…' : 'Run diagnostic'"></span>
                            </button>
                        </div>

                        {{-- Fetch error --}}
                        <div x-show="fetchErr" class="rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700" x-text="'Request failed: ' + fetchErr"></div>

                        {{-- Results --}}
                        <div x-show="ran && steps.length" class="space-y-2">
                            <div class="flex items-center justify-between">
                                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide" x-text="'Results for ' + displayName"></p>
                                <span class="text-xs text-gray-400" x-text="elapsed + 's'"></span>
                            </div>
                            <template x-for="step in steps" :key="step.number">
                                <div :class="{
                                        'bg-green-50 border-green-200': step.status === 'ok',
                                        'bg-amber-50 border-amber-200': step.status === 'warning',
                                        'bg-red-50 border-red-200': step.status === 'error'
                                    }"
                                     class="rounded-lg border overflow-hidden">
                                    {{-- Step header --}}
                                    <button type="button"
                                            @click="
                                                const det = $el.closest('.rounded-lg').querySelector('[data-details]');
                                                const ch  = $el.querySelector('[data-chevron]');
                                                if (!det) return;
                                                const open = !det.classList.contains('hidden');
                                                det.classList.toggle('hidden', open);
                                                if (ch) ch.style.transform = open ? '' : 'rotate(180deg)';
                                            "
                                            class="w-full flex items-center gap-3 px-4 py-3 text-left hover:brightness-95 transition-all">
                                        <span class="flex-shrink-0 inline-flex items-center justify-center w-6 h-6 rounded-full bg-white shadow-sm border border-gray-200 text-xs font-bold text-gray-500"
                                              x-text="step.number"></span>
                                        <span class="flex-1 min-w-0">
                                            <span class="block text-sm font-semibold text-gray-900" x-text="step.title"></span>
                                            <span class="block text-xs text-gray-600 mt-0.5" x-text="step.message"></span>
                                        </span>
                                        <span class="flex-shrink-0 inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium"
                                              :class="{
                                                  'bg-green-100 text-green-800': step.status === 'ok',
                                                  'bg-amber-100 text-amber-800': step.status === 'warning',
                                                  'bg-red-100 text-red-800': step.status === 'error'
                                              }">
                                            <span class="h-1.5 w-1.5 rounded-full"
                                                  :class="{
                                                      'bg-green-500': step.status === 'ok',
                                                      'bg-amber-400': step.status === 'warning',
                                                      'bg-red-500': step.status === 'error'
                                                  }"></span>
                                            <span x-text="step.status === 'ok' ? 'OK' : step.status === 'warning' ? 'Warning' : 'Error'"></span>
                                        </span>
                                        <svg data-chevron
                                             class="h-4 w-4 text-gray-400 flex-shrink-0 transition-transform duration-150"
                                             viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M5.22 8.22a.75.75 0 011.06 0L10 11.94l3.72-3.72a.75.75 0 111.06 1.06l-4.25 4.25a.75.75 0 01-1.06 0L5.22 9.28a.75.75 0 010-1.06z" clip-rule="evenodd"/>
                                        </svg>
                                    </button>

                                    {{-- Step details --}}
                                    <div data-details
                                         :id="'diag-details-' + step.number"
                                         class="hidden border-t px-4 py-3 bg-white bg-opacity-60"
                                         :class="{
                                             'border-green-200': step.status === 'ok',
                                             'border-amber-200': step.status === 'warning',
                                             'border-red-200': step.status === 'error'
                                         }">
                                        {{-- Meta fields --}}
                                        <template x-if="Object.keys(step.data).filter(k => k !== 'events').length > 0">
                                            <dl class="grid grid-cols-[auto_1fr] gap-x-6 gap-y-1 text-xs mb-3">
                                                <template x-for="[key, val] in Object.entries(step.data).filter(([k]) => k !== 'events')" :key="key">
                                                    <template x-if="true">
                                                        <span class="contents">
                                                            <dt class="font-medium text-gray-500 whitespace-nowrap py-0.5" x-text="key"></dt>
                                                            <dd class="text-gray-900 py-0.5" x-text="val ?? '-'"></dd>
                                                        </span>
                                                    </template>
                                                </template>
                                            </dl>
                                        </template>

                                        {{-- Events list --}}
                                        <template x-if="step.data.events && step.data.events.length > 0">
                                            <div>
                                                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-1.5"
                                                   x-text="'Events (' + step.data.events.length + ' shown)'"></p>
                                                <div class="space-y-1.5">
                                                    <template x-for="(ev, i) in step.data.events" :key="i">
                                                        <div class="rounded-md bg-gray-50 border border-gray-200 px-3 py-2 text-xs flex items-start gap-2">
                                                            <div class="flex-1 min-w-0">
                                                                <p class="font-semibold text-gray-800 truncate" x-text="ev.title ?? ev.summary ?? '(no title)'"></p>
                                                                <p class="text-gray-500 mt-0.5" x-text="(ev.start ?? '') + ' → ' + (ev.end ?? '')"></p>
                                                            </div>
                                                            <template x-if="ev.all_day === 'Yes'">
                                                                <span class="flex-shrink-0 rounded bg-amber-100 text-amber-700 px-1.5 py-0.5 text-[10px] font-medium">all-day</span>
                                                            </template>
                                                        </div>
                                                    </template>
                                                </div>
                                            </div>
                                        </template>
                                        <template x-if="step.data.events && step.data.events.length === 0">
                                            <p class="text-xs text-gray-400 italic">No events to show</p>
                                        </template>
                                        {{-- Fallback when data is an empty object {} --}}
                                        <template x-if="!Array.isArray(step.data) && Object.keys(step.data).length === 0">
                                            <p class="text-xs text-gray-400 italic">No additional details available.</p>
                                        </template>
                                    </div>
                                </div>
                            </template>
                        </div>

                        {{-- Empty / not-yet-run state --}}
                        <template x-if="!ran && !running && !fetchErr">
                            <div class="flex flex-col items-center justify-center py-10 text-center">
                                <div class="mb-3 inline-flex h-12 w-12 items-center justify-center rounded-full bg-gray-100">
                                    <svg class="h-6 w-6 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/>
                                    </svg>
                                </div>
                                <p class="text-sm font-medium text-gray-700">Select a display and run the diagnostic</p>
                                <p class="mt-1 text-xs text-gray-400">We'll call the live calendar connection and check it's configured correctly</p>
                            </div>
                        </template>
                    @endif
                </div>

                {{-- Footer --}}
                <div class="flex justify-end border-t border-gray-200 px-6 py-4">
                    <button type="button" @click="show = false"
                            class="rounded-lg bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 transition-colors">
                        Close
                    </button>
                </div>

            </div>
        </div>
    </div>
</div>
