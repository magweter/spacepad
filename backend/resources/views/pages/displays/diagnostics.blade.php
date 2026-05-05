@extends('layouts.base')
@section('title', 'Calendar Sync Diagnostics')
@section('container_class', 'max-w-3xl')

@section('content')
<x-cards.card>
    {{-- Header --}}
    <div class="mb-6">
        <h1 class="text-lg font-semibold leading-6 text-gray-900">Calendar Sync Diagnostics</h1>
        <p class="mt-1 text-sm text-gray-500">Trace the full event pipeline from calendar API to tablet — pinpoint exactly where events disappear.</p>
    </div>

    {{-- Display selector --}}
    @if($displays->isEmpty())
        <div class="rounded-lg bg-amber-50 border border-amber-200 p-4 text-sm text-amber-800">
            No displays found for this workspace. Create a display first.
        </div>
    @else
        <div class="flex flex-col sm:flex-row gap-3 mb-6">
            <div class="flex-1">
                <label for="displaySelect" class="block text-sm font-medium text-gray-700 mb-1">Select display</label>
                <select id="displaySelect"
                        class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                        onchange="onDisplayChange(this.value)">
                    @foreach($displays as $d)
                        <option value="{{ $d->id }}"
                                data-run-url="{{ route('displays.diagnostics.run', $d) }}"
                                {{ $selected && $selected->id === $d->id ? 'selected' : '' }}>
                            {{ $d->name }}
                            @if($d->calendar)
                                —
                                @if($d->calendar->outlook_account_id) Microsoft 365
                                @elseif($d->calendar->google_account_id) Google Calendar
                                @elseif($d->calendar->caldav_account_id) CalDAV
                                @endif
                                @if($d->calendar->room) (room) @endif
                            @else
                                — no calendar
                            @endif
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end">
                <button id="runBtn" onclick="runDiagnostics()"
                        class="inline-flex items-center gap-x-2 rounded-md bg-oxford px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-oxford-600 disabled:opacity-60 whitespace-nowrap">
                    <svg id="runIcon" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M2 10a8 8 0 1116 0A8 8 0 012 10zm6.39-2.908a.75.75 0 01.766.027l3.5 2.25a.75.75 0 010 1.262l-3.5 2.25A.75.75 0 018 12.25v-4.5a.75.75 0 01.39-.658z" clip-rule="evenodd" />
                    </svg>
                    <svg id="spinIcon" class="h-4 w-4 animate-spin hidden" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Run diagnostic
                </button>
            </div>
        </div>

        {{-- Info strip --}}
        <div class="mb-6 rounded-lg bg-blue-50 border border-blue-200 px-4 py-3 flex items-start gap-2">
            <svg class="h-4 w-4 text-blue-400 flex-shrink-0 mt-0.5" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a.75.75 0 000 1.5h.253a.25.25 0 01.244.304l-.459 2.066A1.75 1.75 0 0010.747 15H11a.75.75 0 000-1.5h-.253a.25.25 0 01-.244-.304l.459-2.066A1.75 1.75 0 009.253 9H9z" clip-rule="evenodd" />
            </svg>
            <p class="text-sm text-blue-700">Runs a live check for <strong>today's events</strong>. Calls the external calendar API directly — results are never cached.</p>
        </div>

        {{-- Results area --}}
        <div id="resultsWrap" class="hidden">
            <div class="flex items-center justify-between mb-3">
                <p class="text-sm font-medium text-gray-700" id="resultsLabel"></p>
                <span id="runTime" class="text-xs text-gray-400"></span>
            </div>
            <div id="results" class="space-y-2"></div>
        </div>

        {{-- Fetch error --}}
        <div id="fetchError" class="hidden rounded-lg bg-red-50 border border-red-200 p-4">
            <p class="text-sm font-semibold text-red-800 mb-1">Diagnostic request failed</p>
            <p class="text-sm text-red-700" id="fetchErrorMsg"></p>
        </div>
    @endif
</x-cards.card>

@if($displays->isNotEmpty())
<script>
// Build a map of display id → run URL from the select options
function getRunUrl() {
    const sel = document.getElementById('displaySelect');
    const opt = sel.options[sel.selectedIndex];
    return opt?.dataset?.runUrl ?? null;
}

function onDisplayChange(displayId) {
    // Clear results when a different display is chosen
    document.getElementById('resultsWrap').classList.add('hidden');
    document.getElementById('fetchError').classList.add('hidden');
    document.getElementById('results').innerHTML = '';

    // Update page URL so a refresh lands on the same display
    const url = new URL(window.location);
    url.searchParams.set('display', displayId);
    window.history.replaceState({}, '', url);
}

async function runDiagnostics() {
    const runUrl = getRunUrl();
    if (!runUrl) return;

    const btn        = document.getElementById('runBtn');
    const runIcon    = document.getElementById('runIcon');
    const spinIcon   = document.getElementById('spinIcon');
    const resultsWrap= document.getElementById('resultsWrap');
    const results    = document.getElementById('results');
    const errBox     = document.getElementById('fetchError');
    const runTime    = document.getElementById('runTime');
    const label      = document.getElementById('resultsLabel');
    const selEl      = document.getElementById('displaySelect');
    const displayName= selEl.options[selEl.selectedIndex]?.text?.split('—')[0]?.trim() ?? 'Display';

    btn.disabled = true;
    runIcon.classList.add('hidden');
    spinIcon.classList.remove('hidden');
    resultsWrap.classList.add('hidden');
    errBox.classList.add('hidden');
    results.innerHTML = '';

    const t0 = Date.now();

    try {
        const resp = await fetch(runUrl, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        });
        if (!resp.ok) throw new Error(`HTTP ${resp.status} — ${resp.statusText}`);
        const json = await resp.json();

        const elapsed = ((Date.now() - t0) / 1000).toFixed(1);
        runTime.textContent = `${elapsed}s`;
        label.textContent   = `Results for "${displayName}"`;

        results.innerHTML = json.steps.map(step => renderStep(step)).join('');
        resultsWrap.classList.remove('hidden');

        // Auto-open the first non-ok step
        const firstBad = json.steps.find(s => s.status !== 'ok');
        if (firstBad && Object.keys(firstBad.data).length > 0) {
            openDetails(firstBad.number);
        }
    } catch (err) {
        document.getElementById('fetchErrorMsg').textContent = err.message;
        errBox.classList.remove('hidden');
    } finally {
        btn.disabled = false;
        runIcon.classList.remove('hidden');
        spinIcon.classList.add('hidden');
    }
}

function renderStep(step) {
    const colors = {
        ok:      { bg: 'bg-green-50',  border: 'border-green-200', badge: 'bg-green-100 text-green-800', dot: 'bg-green-500' },
        warning: { bg: 'bg-amber-50',  border: 'border-amber-200', badge: 'bg-amber-100 text-amber-800', dot: 'bg-amber-400' },
        error:   { bg: 'bg-red-50',    border: 'border-red-200',   badge: 'bg-red-100 text-red-800',     dot: 'bg-red-500'   },
    };
    const c           = colors[step.status] ?? colors.warning;
    const statusLabel = { ok: 'OK', warning: 'Warning', error: 'Error' }[step.status] ?? step.status;
    const hasDetails  = step.data && Object.keys(step.data).length > 0;
    const detailsHtml = hasDetails ? renderData(step.data) : '';

    return `
    <div class="rounded-lg border ${c.border} ${c.bg} overflow-hidden">
        <button type="button"
                onclick="toggleDetails(${step.number})"
                class="w-full flex items-center gap-3 px-4 py-3 text-left ${hasDetails ? 'hover:brightness-95' : 'cursor-default'} transition-all">
            <span class="flex-shrink-0 inline-flex items-center justify-center w-7 h-7 rounded-full bg-white shadow-sm border border-gray-200 text-xs font-bold text-gray-500">${step.number}</span>
            <span class="flex-1 min-w-0">
                <span class="block text-sm font-semibold text-gray-900">${escHtml(step.title)}</span>
                <span class="block text-xs text-gray-600 truncate mt-0.5">${escHtml(step.message)}</span>
            </span>
            <span class="flex-shrink-0 inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${c.badge}">
                <span class="mr-1.5 h-1.5 w-1.5 rounded-full ${c.dot} inline-block"></span>
                ${statusLabel}
            </span>
            ${hasDetails ? `<svg id="chevron-${step.number}" class="h-4 w-4 text-gray-400 flex-shrink-0 transition-transform duration-150" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M5.22 8.22a.75.75 0 011.06 0L10 11.94l3.72-3.72a.75.75 0 111.06 1.06l-4.25 4.25a.75.75 0 01-1.06 0L5.22 9.28a.75.75 0 010-1.06z" clip-rule="evenodd" />
            </svg>` : ''}
        </button>
        ${detailsHtml ? `<div id="details-${step.number}" class="hidden border-t ${c.border} px-4 py-3 bg-white bg-opacity-60">${detailsHtml}</div>` : ''}
    </div>`;
}

function renderData(data) {
    const entries     = Object.entries(data);
    const eventsEntry = entries.find(([k]) => k === 'events');
    const metaEntries = entries.filter(([k]) => k !== 'events');

    let html = '';

    if (metaEntries.length > 0) {
        html += '<dl class="grid grid-cols-[auto_1fr] gap-x-6 gap-y-1.5 text-sm mb-3">';
        for (const [key, val] of metaEntries) {
            html += `<dt class="font-medium text-gray-500 whitespace-nowrap">${escHtml(String(key))}</dt>
                     <dd class="text-gray-900">${escHtml(String(val ?? '—'))}</dd>`;
        }
        html += '</dl>';
    }

    if (eventsEntry) {
        const events = eventsEntry[1] ?? [];
        if (events.length > 0) {
            html += `<p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Events (first ${events.length} shown)</p>`;
            html += '<div class="space-y-1.5">';
            for (const ev of events) {
                const isAllDay = ev.all_day === 'Yes';
                html += `<div class="rounded-md bg-gray-50 border border-gray-200 px-3 py-2 text-xs flex items-start gap-2">
                    <div class="flex-1 min-w-0">
                        <p class="font-semibold text-gray-800 truncate">${escHtml(ev.title ?? ev.summary ?? '(no title)')}</p>
                        <p class="text-gray-500 mt-0.5">${escHtml(ev.start ?? '')} → ${escHtml(ev.end ?? '')}</p>
                    </div>
                    ${isAllDay ? '<span class="flex-shrink-0 rounded bg-amber-100 text-amber-700 px-1.5 py-0.5 text-[10px] font-medium">all-day</span>' : ''}
                </div>`;
            }
            html += '</div>';
        } else {
            html += '<p class="text-sm text-gray-400 italic">No events to show</p>';
        }
    }

    return html;
}

function openDetails(stepNum) {
    const el      = document.getElementById(`details-${stepNum}`);
    const chevron = document.getElementById(`chevron-${stepNum}`);
    if (!el) return;
    el.classList.remove('hidden');
    if (chevron) chevron.style.transform = 'rotate(180deg)';
}

function toggleDetails(stepNum) {
    const el      = document.getElementById(`details-${stepNum}`);
    const chevron = document.getElementById(`chevron-${stepNum}`);
    if (!el) return;
    const isOpen = !el.classList.contains('hidden');
    el.classList.toggle('hidden', isOpen);
    if (chevron) chevron.style.transform = isOpen ? '' : 'rotate(180deg)';
}

function escHtml(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}
</script>
@endif
@endsection
