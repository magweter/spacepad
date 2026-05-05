@extends('layouts.base')
@section('title', 'Diagnostics — ' . $display->name)
@section('container_class', 'max-w-3xl')

@section('content')
<x-cards.card>
    {{-- Header --}}
    <div class="sm:flex sm:items-center mb-6">
        <div class="sm:flex-auto">
            <h1 class="text-lg font-semibold leading-6 text-gray-900">Calendar Sync Diagnostics</h1>
            <p class="mt-1 text-sm text-gray-500">Step-by-step pipeline for "{{ $display->name }}" — shows exactly where events get lost.</p>
        </div>
        <div class="mt-4 sm:ml-16 sm:mt-0 sm:flex-none">
            <a href="{{ route('dashboard') }}"
               class="inline-flex items-center gap-x-1.5 rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M17 10a.75.75 0 01-.75.75H5.612l4.158 3.96a.75.75 0 11-1.04 1.08l-5.5-5.25a.75.75 0 010-1.08l5.5-5.25a.75.75 0 111.04 1.08L5.612 9.25H16.25A.75.75 0 0117 10z" clip-rule="evenodd" />
                </svg>
                Back
            </a>
        </div>
    </div>

    {{-- Info banner --}}
    <div class="mb-6 rounded-lg bg-blue-50 border border-blue-200 p-4">
        <div class="flex">
            <svg class="h-5 w-5 text-blue-400 flex-shrink-0 mt-0.5" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a.75.75 0 000 1.5h.253a.25.25 0 01.244.304l-.459 2.066A1.75 1.75 0 0010.747 15H11a.75.75 0 000-1.5h-.253a.25.25 0 01-.244-.304l.459-2.066A1.75 1.75 0 009.253 9H9z" clip-rule="evenodd" />
            </svg>
            <div class="ml-3 text-sm text-blue-700">
                This tool runs a live check <strong>for today's events</strong>. It calls the external calendar API directly and shows the full pipeline from raw API response to what the tablet receives.
            </div>
        </div>
    </div>

    {{-- Run button --}}
    <div class="flex items-center gap-4 mb-8">
        <button id="runBtn" onclick="runDiagnostics()"
                class="inline-flex items-center gap-x-2 rounded-md bg-oxford px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-oxford-600 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 disabled:opacity-60">
            <svg id="runIcon" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M2 10a8 8 0 1116 0A8 8 0 012 10zm6.39-2.908a.75.75 0 01.766.027l3.5 2.25a.75.75 0 010 1.262l-3.5 2.25A.75.75 0 018 12.25v-4.5a.75.75 0 01.39-.658z" clip-rule="evenodd" />
            </svg>
            <svg id="spinIcon" class="h-4 w-4 animate-spin hidden" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            Run diagnostic
        </button>
        <span id="runTime" class="text-xs text-gray-400 hidden"></span>
    </div>

    {{-- Results --}}
    <div id="results" class="space-y-3 hidden">
    </div>

    {{-- Error state --}}
    <div id="fetchError" class="hidden rounded-lg bg-red-50 border border-red-200 p-4">
        <p class="text-sm text-red-700" id="fetchErrorMsg"></p>
    </div>
</x-cards.card>

<script>
const runUrl = '{{ route('displays.diagnostics.run', $display) }}';

async function runDiagnostics() {
    const btn       = document.getElementById('runBtn');
    const runIcon   = document.getElementById('runIcon');
    const spinIcon  = document.getElementById('spinIcon');
    const results   = document.getElementById('results');
    const errBox    = document.getElementById('fetchError');
    const runTime   = document.getElementById('runTime');

    btn.disabled    = true;
    runIcon.classList.add('hidden');
    spinIcon.classList.remove('hidden');
    results.classList.add('hidden');
    errBox.classList.add('hidden');
    results.innerHTML = '';

    const t0 = Date.now();

    try {
        const resp = await fetch(runUrl, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        });
        if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
        const json = await resp.json();

        const elapsed = ((Date.now() - t0) / 1000).toFixed(1);
        runTime.textContent = `Completed in ${elapsed}s`;
        runTime.classList.remove('hidden');

        results.innerHTML = json.steps.map(step => renderStep(step)).join('');
        results.classList.remove('hidden');
    } catch (err) {
        document.getElementById('fetchErrorMsg').textContent = 'Diagnostic failed: ' + err.message;
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
        error:   { bg: 'bg-red-50',    border: 'border-red-200',   badge: 'bg-red-100 text-red-800',     dot: 'bg-red-500' },
    };
    const c = colors[step.status] || colors.warning;
    const statusLabel = { ok: 'OK', warning: 'Warning', error: 'Error' }[step.status];

    const dataHtml = renderData(step.data, step.number);

    return `
    <div class="rounded-lg border ${c.border} ${c.bg} overflow-hidden">
        <button type="button"
            onclick="toggleDetails(${step.number})"
            class="w-full flex items-center gap-3 px-4 py-3 text-left hover:brightness-95 transition-all">
            <span class="flex-shrink-0 inline-flex items-center justify-center w-7 h-7 rounded-full bg-white shadow-sm border border-gray-200 text-xs font-bold text-gray-500">${step.number}</span>
            <span class="flex-1 text-sm font-semibold text-gray-900">${escHtml(step.title)}</span>
            <span class="flex-shrink-0 inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${c.badge}">
                <span class="mr-1.5 h-1.5 w-1.5 rounded-full ${c.dot} inline-block"></span>
                ${statusLabel}
            </span>
            <svg id="chevron-${step.number}" class="h-4 w-4 text-gray-400 flex-shrink-0 transition-transform ${Object.keys(step.data).length > 0 ? '' : 'opacity-0'}" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M5.22 8.22a.75.75 0 011.06 0L10 11.94l3.72-3.72a.75.75 0 111.06 1.06l-4.25 4.25a.75.75 0 01-1.06 0L5.22 9.28a.75.75 0 010-1.06z" clip-rule="evenodd" />
            </svg>
        </button>
        <div class="px-4 pb-1">
            <p class="text-sm text-gray-700 pb-2">${escHtml(step.message)}</p>
        </div>
        ${dataHtml ? `<div id="details-${step.number}" class="hidden border-t ${c.border} px-4 py-3 bg-white bg-opacity-60">${dataHtml}</div>` : ''}
    </div>`;
}

function renderData(data, stepNum) {
    if (!data || Object.keys(data).length === 0) return '';

    const entries = Object.entries(data);
    const eventsEntry = entries.find(([k]) => k === 'events');
    const metaEntries = entries.filter(([k]) => k !== 'events');

    let html = '';

    if (metaEntries.length > 0) {
        html += '<dl class="grid grid-cols-2 gap-x-6 gap-y-1.5 text-sm mb-3">';
        for (const [key, val] of metaEntries) {
            html += `<dt class="font-medium text-gray-500">${escHtml(String(key))}</dt>
                     <dd class="text-gray-900">${escHtml(String(val ?? '—'))}</dd>`;
        }
        html += '</dl>';
    }

    if (eventsEntry && eventsEntry[1]?.length > 0) {
        const events = eventsEntry[1];
        html += `<p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Events (up to 10 shown)</p>`;
        html += '<div class="space-y-1.5">';
        for (const ev of events) {
            html += `<div class="rounded-md bg-gray-50 border border-gray-200 px-3 py-2 text-xs">
                <p class="font-semibold text-gray-800 truncate">${escHtml(ev.title ?? ev.summary ?? '(no title)')}</p>
                <p class="text-gray-500 mt-0.5">${escHtml(ev.start ?? '')} → ${escHtml(ev.end ?? '')}${ev.all_day === 'Yes' ? ' <span class="inline-block ml-1 text-amber-600">(all-day)</span>' : ''}</p>
            </div>`;
        }
        html += '</div>';
    } else if (eventsEntry && eventsEntry[1]?.length === 0) {
        html += '<p class="text-sm text-gray-400 italic">No events to display</p>';
    }

    return html;
}

function toggleDetails(stepNum) {
    const el      = document.getElementById(`details-${stepNum}`);
    const chevron = document.getElementById(`chevron-${stepNum}`);
    if (!el) return;
    const open = !el.classList.contains('hidden');
    el.classList.toggle('hidden', open);
    chevron.style.transform = open ? '' : 'rotate(180deg)';
}

function escHtml(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}
</script>
@endsection
