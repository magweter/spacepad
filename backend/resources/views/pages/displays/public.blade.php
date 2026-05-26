@extends('layouts.display')
@section('title', $display->display_name . ' — ' . config('app.name'))

@php
    $showMeetingTitle = $display->getShowMeetingTitle();
    $fontFamily = $display->getFontFamily();
    $bookingEnabled = $display->isBookingEnabled();

    $overlayClass = match($roomStatus) {
        'reserved'     => 'bg-red-900/75',
        'transitioning' => 'bg-amber-900/75',
        default        => 'bg-emerald-900/75',
    };
    $statusBadgeClass = match($roomStatus) {
        'reserved'     => 'bg-red-500 text-white',
        'transitioning' => 'bg-amber-500 text-white',
        default        => 'bg-emerald-500 text-white',
    };
    $statusDotClass = match($roomStatus) {
        'reserved'     => 'bg-red-400',
        'transitioning' => 'bg-amber-400',
        default        => 'bg-emerald-400',
    };
    $statusText = match($roomStatus) {
        'reserved'     => ($display->getReservedText() ?: 'In Use'),
        'transitioning' => ($display->getTransitioningText() ?: 'Starting Soon'),
        default        => ($display->getAvailableText() ?: 'Room Available'),
    };
    $availableText = $display->getAvailableText() ?: 'Room Available';
@endphp

@push('styles')
@if($fontFamily !== 'Inter')
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family={{ urlencode($fontFamily) }}:wght@400;500;600;700;800&display=swap" rel="stylesheet">
@endif
<style>
    body { font-family: '{{ $fontFamily }}', sans-serif; }
</style>
@endpush

@section('content')
{{-- Offline indicator --}}
<div id="offline-banner" class="hidden fixed bottom-4 left-1/2 -translate-x-1/2 z-50 flex items-center gap-2 px-4 py-2 rounded-full bg-gray-900/90 backdrop-blur-sm text-white text-sm font-medium shadow-lg pointer-events-none">
    <span class="inline-block h-2 w-2 rounded-full bg-red-400 animate-pulse flex-shrink-0"></span>
    Offline &mdash; showing last known state
</div>

<div class="flex h-full">

    {{-- ===== LEFT PANEL: status & booking ===== --}}
    <div class="relative flex-1 min-w-0 flex flex-col">

        {{-- Background image --}}
        @if($backgroundUrl)
            <div class="absolute inset-0 bg-cover bg-center" style="background-image: url('{{ $backgroundUrl }}')"></div>
        @else
            <div class="absolute inset-0 {{ match($roomStatus) { 'reserved' => 'bg-red-950', 'transitioning' => 'bg-amber-950', default => 'bg-emerald-950' } }}"></div>
        @endif

        {{-- Color overlay --}}
        <div class="absolute inset-0 {{ $overlayClass }}"></div>

        {{-- Content --}}
        <div class="relative flex flex-col h-full p-8 select-none">

            {{-- Header: logo + room name --}}
            <div class="flex items-start justify-between flex-shrink-0">
                <div>
                    @if($logoUrl)
                        <img src="{{ $logoUrl }}" alt="Logo" class="h-10 w-auto object-contain">
                    @else
                        <img src="/images/logo-white.svg" alt="{{ config('app.name') }}" class="h-8 w-auto opacity-70">
                    @endif
                </div>
                <div class="text-right">
                    <div class="text-white/90 font-semibold text-lg">{{ $display->display_name }}</div>
                    <div id="live-date" class="text-white/60 text-sm"></div>
                </div>
            </div>

            {{-- Center: clock + status --}}
            <div class="flex-1 flex flex-col items-center justify-center text-center">

                {{-- Clock --}}
                <div id="live-clock" class="text-8xl font-bold text-white tabular-nums tracking-tight"></div>

                {{-- Status badge --}}
                <div class="mt-8 flex items-center gap-3 px-6 py-3 rounded-full {{ $statusBadgeClass }} text-xl font-semibold shadow-lg">
                    <span class="inline-block h-3 w-3 rounded-full {{ $statusDotClass }} animate-pulse"></span>
                    {{ $statusText }}
                </div>

                {{-- Current event info (when reserved) --}}
                @if($roomStatus === 'reserved' && $currentEvent)
                    <div class="mt-4 text-white/80 text-base">
                        @if($showMeetingTitle && $currentEvent->summary)
                            <div class="font-medium text-white text-lg">{{ $currentEvent->summary }}</div>
                        @endif
                        <div class="mt-1">
                            {{ $currentEvent->start->setTimezone($timezone)->format('g:i A') }}
                            &ndash;
                            {{ $currentEvent->end->setTimezone($timezone)->format('g:i A') }}
                        </div>
                    </div>
                @endif

                {{-- Next event info (when transitioning) --}}
                @if($roomStatus === 'transitioning' && $nextEvent)
                    <div class="mt-4 text-white/80 text-base">
                        @if($showMeetingTitle && $nextEvent->summary)
                            <div class="font-medium text-white text-lg">{{ $nextEvent->summary }}</div>
                        @endif
                        <div class="mt-1">
                            Starts at {{ $nextEvent->start->setTimezone($timezone)->format('g:i A') }}
                        </div>
                    </div>
                @endif

            </div>

            {{-- Booking buttons (available only) --}}
            @if($bookingEnabled && $roomStatus === 'available')
                <div class="flex-shrink-0">
                    <div class="flex flex-wrap gap-3 justify-center">
                        <button onclick="quickBook(15)"
                            class="px-5 py-3 rounded-lg bg-white/20 hover:bg-white/30 backdrop-blur text-white font-semibold text-sm transition-colors border border-white/30">
                            15 min
                        </button>
                        <button onclick="quickBook(30)"
                            class="px-5 py-3 rounded-lg bg-white/20 hover:bg-white/30 backdrop-blur text-white font-semibold text-sm transition-colors border border-white/30">
                            30 min
                        </button>
                        <button onclick="quickBook(45)"
                            class="px-5 py-3 rounded-lg bg-white/20 hover:bg-white/30 backdrop-blur text-white font-semibold text-sm transition-colors border border-white/30">
                            45 min
                        </button>
                        <button onclick="quickBook(60)"
                            class="px-5 py-3 rounded-lg bg-white/20 hover:bg-white/30 backdrop-blur text-white font-semibold text-sm transition-colors border border-white/30">
                            60 min
                        </button>
                        <button onclick="openBookModal()"
                            class="px-5 py-3 rounded-lg bg-emerald-500 hover:bg-emerald-400 text-white font-semibold text-sm transition-colors">
                            Reserve&hellip;
                        </button>
                    </div>
                </div>
            @endif

        </div>
    </div>

    {{-- ===== RIGHT PANEL: timeline calendar ===== --}}
    @php
        $tlStart = 6;   // 6 am
        $tlEnd   = 18;  // 6 pm
        $hourPx  = 64;  // pixels per hour slot
        $totalPx = ($tlEnd - $tlStart) * $hourPx; // 768px
        $nowTs   = now();
    @endphp
    <div class="w-80 bg-gray-900 flex flex-col flex-shrink-0 overflow-hidden">

        {{-- Header --}}
        <div class="flex-shrink-0 px-5 py-4 border-b border-white/10 flex items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-gray-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
            </svg>
            <span id="schedule-date" class="text-sm font-medium text-gray-100"></span>
        </div>

        {{-- Scrollable timeline --}}
        <div class="flex-1 overflow-y-auto" id="timeline-scroll">
            <div class="relative" style="height: {{ $totalPx }}px">

                {{-- Hour grid lines + labels --}}
                @for ($h = $tlStart; $h <= $tlEnd; $h++)
                @php
                    $label = match(true) {
                        $h === 0  => '12am',
                        $h === 12 => '12pm',
                        $h < 12   => $h . 'am',
                        default   => ($h - 12) . 'pm',
                    };
                    $gridTop = ($h - $tlStart) * $hourPx;
                @endphp
                <div class="absolute left-0 right-0 flex items-start" style="top: {{ $gridTop }}px">
                    <span class="w-12 pr-2 flex-shrink-0 text-right text-xs text-gray-500 -mt-2 select-none">{{ $label }}</span>
                    <div class="flex-1 border-t border-white/10"></div>
                </div>
                @endfor

                {{-- Event blocks --}}
                @foreach ($events as $event)
                @php
                    $evStart = $event->start->setTimezone($timezone);
                    $evEnd   = $event->end->setTimezone($timezone);
                    $sh = $evStart->hour + $evStart->minute / 60;
                    $eh = $evEnd->hour   + $evEnd->minute   / 60;
                @endphp
                @continue($eh <= $tlStart || $sh >= $tlEnd)
                @php
                    $cs = max($sh, $tlStart);
                    $ce = min($eh, $tlEnd);
                    $evTopPx    = ($cs - $tlStart) * $hourPx + 1;
                    $evHeightPx = max(22, ($ce - $cs) * $hourPx - 2);
                    $isCurrent  = $event->start <= $nowTs && $event->end > $nowTs;
                    $isPast     = $event->end <= $nowTs;
                    $showTitle  = $showMeetingTitle && !empty($event->summary) && $evHeightPx > 36;
                @endphp
                <div class="absolute rounded overflow-hidden"
                     style="top: {{ $evTopPx }}px; height: {{ $evHeightPx }}px; left: 3rem; right: 0.5rem;
                            background: {{ $isCurrent ? 'rgba(59,130,246,0.35)' : ($isPast ? 'rgba(255,255,255,0.05)' : 'rgba(255,255,255,0.11)') }};">
                    @if($isCurrent)
                    <div class="absolute left-0 top-0 bottom-0 w-0.5 bg-blue-400"></div>
                    @endif
                    <div class="{{ $isCurrent ? 'pl-3 pr-2' : 'px-2' }} py-1 h-full flex flex-col justify-center leading-none">
                        <span class="text-xs {{ $isPast ? 'text-gray-600' : 'text-gray-300' }} truncate">
                            {{ $evStart->format('g:i') }}&ndash;{{ $evEnd->format('g:i A') }}
                        </span>
                        @if($showTitle)
                        <span class="text-xs font-semibold {{ $isPast ? 'text-gray-500' : 'text-white' }} truncate mt-0.5">
                            {{ $event->summary }}
                        </span>
                        @endif
                    </div>
                </div>
                @endforeach

                {{-- Current-time indicator (positioned by JS) --}}
                <div id="now-line" class="absolute left-0 right-0 flex items-center pointer-events-none z-20" style="display:none">
                    <div class="w-12 flex-shrink-0"></div>
                    <div class="w-2.5 h-2.5 rounded-full bg-red-500 flex-shrink-0 -mr-1.5 z-10 shadow-sm shadow-red-500/50"></div>
                    <div class="flex-1 h-px bg-red-500"></div>
                </div>

            </div>
        </div>
    </div>

</div>

{{-- ===== Booking Modal ===== --}}
@if($bookingEnabled && $roomStatus === 'available')
<div id="bookModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm">
    <div class="bg-white rounded-2xl shadow-2xl w-80 p-6 mx-4">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Reserve Room</h3>

        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">Meeting title</label>
            <input type="text" id="bookTitle" placeholder="Reserved"
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
        </div>

        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-2">Duration</label>
            <div class="grid grid-cols-3 gap-2">
                @foreach([15, 30, 45, 60, 90, 120] as $dur)
                    <button onclick="bookFromModal({{ $dur }})"
                        class="rounded-lg border border-gray-200 py-2 text-sm font-medium text-gray-700 hover:bg-emerald-50 hover:border-emerald-400 hover:text-emerald-700 transition-colors">
                        {{ $dur < 60 ? $dur . ' min' : ($dur / 60) . 'h' }}
                    </button>
                @endforeach
            </div>
        </div>

        <div id="bookError" class="hidden mb-3 text-sm text-red-600 rounded-lg bg-red-50 px-3 py-2"></div>

        <button onclick="closeBookModal()"
            class="w-full rounded-lg bg-gray-100 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200 transition-colors">
            Cancel
        </button>
    </div>
</div>
@endif

@push('scripts')
<script>
const DISPLAY_TIMEZONE = @json($timezone);

// Live clock
function updateClock() {
    const now = new Date();
    const timeStr = new Intl.DateTimeFormat('en-GB', {
        hour: '2-digit',
        minute: '2-digit',
        hour12: false,
        timeZone: DISPLAY_TIMEZONE,
    }).format(now);
    document.getElementById('live-clock').textContent = timeStr;

    const dateStr = new Intl.DateTimeFormat('en-US', {
        weekday: 'long',
        month: 'long',
        day: 'numeric',
        timeZone: DISPLAY_TIMEZONE,
    }).format(now);
    const el = document.getElementById('live-date');
    if (el) el.textContent = dateStr;
    const sd = document.getElementById('schedule-date');
    if (sd) sd.textContent = dateStr;
}
updateClock();
setInterval(updateClock, 1000);

// Poll the status endpoint and reload when reachable, or show offline banner when not.
const STATUS_URL = @json(route('displays.public.status', $token));
let pollTimeout;

function scheduleNextPoll(delayMs) {
    clearTimeout(pollTimeout);
    pollTimeout = setTimeout(poll, delayMs);
}

function poll() {
    fetch(STATUS_URL, { cache: 'no-store' })
        .then(function(r) {
            if (!r.ok) throw new Error('bad');
            return r.json();
        })
        .then(function() {
            // Server is reachable — reload for fresh data.
            location.reload();
        })
        .catch(function() {
            // Network failure — show offline indicator and retry sooner.
            document.getElementById('offline-banner').classList.remove('hidden');
            scheduleNextPoll(10000);
        });
}

scheduleNextPoll(30000);

// Timeline: now-line position and auto-scroll
const TL_START_H = 6;
const TL_END_H   = 18;
const TL_HOUR_PX = 64;

function updateNowLine() {
    const now = new Date();
    const parts = new Intl.DateTimeFormat('en-GB', {
        hour: 'numeric', minute: 'numeric', hour12: false,
        timeZone: DISPLAY_TIMEZONE,
    }).formatToParts(now);
    const h = parseInt(parts.find(p => p.type === 'hour').value, 10);
    const m = parseInt(parts.find(p => p.type === 'minute').value, 10);
    const decimal = h + m / 60;
    const line = document.getElementById('now-line');
    if (!line) return;
    if (decimal >= TL_START_H && decimal <= TL_END_H) {
        line.style.top = ((decimal - TL_START_H) * TL_HOUR_PX) + 'px';
        line.style.display = 'flex';
    } else {
        line.style.display = 'none';
    }
}

function scrollToNow() {
    const container = document.getElementById('timeline-scroll');
    const line = document.getElementById('now-line');
    if (!container || !line || line.style.display === 'none') return;
    const lineTop = parseFloat(line.style.top) || 0;
    container.scrollTop = Math.max(0, lineTop - container.clientHeight * 0.35);
}

updateNowLine();
setTimeout(scrollToNow, 50);
setInterval(updateNowLine, 60000);

@if($bookingEnabled && $roomStatus === 'available')
const BOOK_URL = '{{ route('displays.public.book', $token) }}';
const CSRF = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

function quickBook(duration) {
    doBook(duration, 'Reserved');
}

function openBookModal() {
    document.getElementById('bookModal').classList.remove('hidden');
    document.getElementById('bookTitle').focus();
    document.getElementById('bookError').classList.add('hidden');
}

function closeBookModal() {
    document.getElementById('bookModal').classList.add('hidden');
}

function bookFromModal(duration) {
    const title = document.getElementById('bookTitle').value.trim() || 'Reserved';
    doBook(duration, title);
}

function showBookError(msg) {
    const el = document.getElementById('bookError');
    if (!el) return;
    el.textContent = msg;
    el.classList.remove('hidden');
}

function doBook(duration, summary) {
    fetch(BOOK_URL, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': CSRF,
        },
        body: JSON.stringify({ duration, summary }),
    })
    .then(async (r) => {
        const contentType = r.headers.get('content-type') || '';
        const data = contentType.includes('application/json') ? await r.json() : {};
        if (!r.ok) {
            throw new Error(data.message || 'Booking failed. Please try again.');
        }
        return data;
    })
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            showBookError(data.message || 'Booking failed. Please try again.');
        }
    })
    .catch((err) => {
        showBookError(err.message || 'Network error. Please try again.');
    });
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeBookModal();
});
@endif
</script>
@endpush
@endsection
