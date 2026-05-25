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
    $timezone = $currentEvent?->timezone ?? $nextEvent?->timezone ?? config('app.timezone');
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

    {{-- ===== RIGHT PANEL: today's schedule ===== --}}
    <div class="w-80 bg-white border-l border-gray-200 flex flex-col flex-shrink-0 overflow-hidden">
        <div class="flex-shrink-0 px-5 py-4 border-b border-gray-100">
            <h2 class="font-semibold text-gray-800">Today's Schedule</h2>
            <p id="schedule-date" class="text-sm text-gray-500 mt-0.5"></p>
        </div>

        <div class="flex-1 overflow-y-auto py-3">
            @forelse($events as $event)
                @php
                    $now = now();
                    $isCurrent = $event->start <= $now && $event->end > $now;
                    $isPast = $event->end <= $now;
                    $eventTz = $event->timezone ?? config('app.timezone');
                @endphp
                <div class="px-4 py-3 {{ $isCurrent ? 'bg-blue-50 border-l-4 border-blue-500' : ($isPast ? 'opacity-50' : '') }}">
                    <div class="flex items-start gap-3">
                        <div class="flex-shrink-0 mt-0.5">
                            @if($isCurrent)
                                <span class="inline-block h-2.5 w-2.5 rounded-full bg-blue-500 mt-1 animate-pulse"></span>
                            @elseif($isPast)
                                <span class="inline-block h-2.5 w-2.5 rounded-full bg-gray-300 mt-1"></span>
                            @else
                                <span class="inline-block h-2.5 w-2.5 rounded-full bg-gray-400 mt-1"></span>
                            @endif
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="text-xs text-gray-500 font-medium">
                                {{ $event->start->setTimezone($eventTz)->format('g:i A') }}
                                &ndash;
                                {{ $event->end->setTimezone($eventTz)->format('g:i A') }}
                                @if($isCurrent)
                                    <span class="ml-1 inline-flex items-center rounded-full bg-blue-100 px-1.5 py-0.5 text-xs font-medium text-blue-700">Now</span>
                                @endif
                            </div>
                            @if($showMeetingTitle && $event->summary)
                                <div class="text-sm font-medium text-gray-800 mt-0.5 truncate">{{ $event->summary }}</div>
                            @else
                                <div class="text-sm font-medium text-gray-800 mt-0.5">Meeting</div>
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <div class="px-5 py-8 text-center">
                    <div class="text-gray-400 text-sm">No meetings scheduled today</div>
                </div>
            @endforelse
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

// Auto-reload every 30 seconds to refresh event data
setTimeout(function() { location.reload(); }, 30000);

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
            const errEl = document.getElementById('bookError');
            if (errEl) {
                errEl.textContent = data.message || 'Booking failed. Please try again.';
                errEl.classList.remove('hidden');
            } else {
                alert(data.message || 'Booking failed.');
            }
        }
    })
    .catch((err) => {
        alert(err.message || 'Network error. Please try again.');
    });
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeBookModal();
});
@endif
</script>
@endpush
@endsection
