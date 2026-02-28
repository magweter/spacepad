@extends('layouts.blank')
@section('page')
<div class="min-h-screen bg-gray-900 text-white p-8 board-dark" id="board-container" data-theme="{{ $board->theme ?? 'dark' }}">
    {{-- Header --}}
    <div class="flex items-center justify-between mb-8">
        <div class="flex items-center gap-4">
            @if($board->logo)
                <img src="{{ route('boards.images.logo', $board) }}?v={{ $board->updated_at->timestamp }}" alt="Board logo" class="h-12 w-auto object-contain">
            @else
                <div class="h-12 w-12 rounded-lg bg-blue-600 flex items-center justify-center text-white font-bold text-lg">
                    {{ strtoupper(substr($board->name, 0, 1)) }}
                </div>
            @endif
            <div>
                <h1 class="text-2xl font-bold tracking-wide">Meeting Room Availability</h1>
                <p class="text-md text-gray-400 mt-1 board-text-secondary">{{ $board->name }}</p>
            </div>
        </div>
        <div class="text-right">
            <div id="current-time" class="text-2xl font-mono font-semibold"></div>
            <div id="current-date" class="text-sm text-gray-400 mt-1 board-text-tertiary"></div>
        </div>
    </div>

    {{-- Room List --}}
    <div class="space-y-2" id="displays-list">
        @forelse($displays as $displayData)
            @php
                $display = $displayData['display'];
                $status = $displayData['status'];
                $statusText = $displayData['statusText'];
                $currentEvent = $displayData['currentEvent'];
                $nextEvent = $displayData['nextEvent'];
                
                // Status colors
                $statusBarColor = match($status) {
                    'busy' => 'bg-red-500',
                    'transitioning' => 'bg-amber-500',
                    'error' => 'bg-gray-500',
                    default => 'bg-green-500',
                };
            @endphp
            <div class="bg-gray-800 rounded-lg p-6 flex items-center gap-6 hover:bg-gray-750 transition-colors board-card">
                {{-- Status Indicator Bar --}}
                <div class="w-1 h-20 {{ $statusBarColor }} rounded-full flex-shrink-0"></div>
                
                {{-- Status Text --}}
                <div class="w-24 flex-shrink-0">
                    <span class="text-lg font-semibold uppercase">{{ $statusText }}</span>
                </div>
                
                {{-- Room Name --}}
                <div class="flex-1 min-w-0">
                    <h3 class="text-xl font-bold mb-2">{{ $display->display_name ?: $display->name }}</h3>
                    
                    @if($currentEvent)
                        {{-- Current Event --}}
                        <div class="text-gray-300 board-text-primary">
                            <div class="font-medium">{{ $currentEvent['summary'] }}</div>
                            <div class="text-sm text-gray-400 mt-1 board-text-secondary">
                                <span class="event-time" data-start="{{ $currentEvent['start']->toIso8601String() }}" data-end="{{ $currentEvent['end']->toIso8601String() }}"></span>
                                @if($currentEvent['organizer'] !== 'Unknown')
                                    / {{ $currentEvent['organizer'] }}
                                @endif
                            </div>
                        </div>
                    @elseif($nextEvent)
                        {{-- Next Up Event --}}
                        <div class="text-gray-300 board-text-primary">
                            <div class="text-amber-400 font-medium board-text-accent">Next Up: {{ $nextEvent['summary'] }}</div>
                            <div class="text-sm text-gray-400 mt-1 board-text-secondary">
                                <span class="event-time" data-start="{{ $nextEvent['start']->toIso8601String() }}" data-end="{{ $nextEvent['end']->toIso8601String() }}"></span>
                                @if($nextEvent['organizer'] !== 'Unknown')
                                    / {{ $nextEvent['organizer'] }}
                                @endif
                            </div>
                        </div>
                    @else
                        {{-- Available --}}
                        <div class="text-gray-400 text-sm board-text-secondary">No upcoming events</div>
                    @endif
                </div>
                
                {{-- Display Icon (optional) --}}
                <div class="flex-shrink-0">
                    <x-icons.display class="h-6 w-6 text-gray-600 board-icon" />
                </div>
            </div>
        @empty
            <div class="bg-gray-800 rounded-lg p-12 text-center board-card">
                <p class="text-gray-400 text-lg board-text-secondary">No displays available for this board.</p>
            </div>
        @endforelse
    </div>
</div>
@endsection

@push('styles')
<style>
    /* Dark mode (default) */
    .board-dark {
        background-color: #111827;
        color: #ffffff;
    }
    .board-dark .board-card {
        background-color: #1f2937;
    }
    .board-dark .board-card:hover {
        background-color: #374151;
    }
    .board-dark .board-text-primary {
        color: #d1d5db;
    }
    .board-dark .board-text-secondary {
        color: #9ca3af;
    }
    .board-dark .board-text-tertiary {
        color: #6b7280;
    }
    .board-dark .board-text-accent {
        color: #fbbf24;
    }
    .board-dark .board-icon {
        color: #4b5563;
    }
    .board-dark .board-button {
        background-color: #1f2937;
    }
    .board-dark .board-button:hover {
        background-color: #374151;
    }

    /* Light mode */
    .board-light {
        background-color: #f9fafb;
        color: #111827;
    }
    .board-light .board-card {
        background-color: #ffffff;
        border: 1px solid #e5e7eb;
    }
    .board-light .board-card:hover {
        background-color: #f3f4f6;
    }
    .board-light .board-text-primary {
        color: #374151;
    }
    .board-light .board-text-secondary {
        color: #6b7280;
    }
    .board-light .board-text-tertiary {
        color: #9ca3af;
    }
    .board-light .board-text-accent {
        color: #d97706;
    }
    .board-light .board-icon {
        color: #d1d5db;
    }
    .board-light .board-button {
        background-color: #ffffff;
        border: 1px solid #e5e7eb;
    }
    .board-light .board-button:hover {
        background-color: #f3f4f6;
    }
</style>
@endpush

@push('scripts')
<script>
    // Theme management - use theme from database
    function getSystemTheme() {
        return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    }

    function setTheme(theme) {
        const container = document.getElementById('board-container');
        
        // If theme is 'system', use browser preference
        if (theme === 'system') {
            theme = getSystemTheme();
        }
        
        if (theme === 'light') {
            container.classList.remove('board-dark');
            container.classList.add('board-light');
        } else {
            container.classList.remove('board-light');
            container.classList.add('board-dark');
        }
    }

    // Initialize theme on page load from database
    document.addEventListener('DOMContentLoaded', function() {
        const container = document.getElementById('board-container');
        const theme = container.dataset.theme || 'dark';
        setTheme(theme);
        
        // Listen for system theme changes if using system preference
        if (theme === 'system' && window.matchMedia) {
            const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
            const handleThemeChange = function() {
                setTheme('system');
            };
            // Modern browsers
            if (mediaQuery.addEventListener) {
                mediaQuery.addEventListener('change', handleThemeChange);
            } else {
                // Fallback for older browsers
                mediaQuery.addListener(handleThemeChange);
            }
        }
    });

    // Format time using browser locale (without seconds)
    function formatTime(date) {
        return date.toLocaleTimeString(undefined, { 
            hour: 'numeric', 
            minute: '2-digit'
        });
    }

    // Format time range using browser locale
    function formatTimeRange(startDate, endDate) {
        const start = formatTime(startDate);
        const end = formatTime(endDate);
        return `${start} - ${end}`;
    }

    // Format date using browser locale
    function formatDate(date) {
        return date.toLocaleDateString(undefined, {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
    }

    // Update current time every second
    function updateTime() {
        const now = new Date();
        // Format current time with seconds for the clock display
        const timeString = now.toLocaleTimeString(undefined, { 
            hour: 'numeric', 
            minute: '2-digit', 
            second: '2-digit'
        });
        document.getElementById('current-time').textContent = timeString;
        document.getElementById('current-date').textContent = formatDate(now);
    }
    
    // Format all event times using browser locale (without seconds)
    function formatEventTimes() {
        document.querySelectorAll('.event-time').forEach(element => {
            const start = new Date(element.dataset.start);
            const end = new Date(element.dataset.end);
            element.textContent = formatTimeRange(start, end);
        });
    }
    
    // Initialize on page load
    updateTime();
    formatEventTimes();
    
    // Update every second
    setInterval(updateTime, 1000);
    
    // Auto-refresh display data every 30 seconds (full page reload for simplicity)
    let refreshInterval;
    
    function refreshDisplayData() {
        // Simple full page reload - more reliable than parsing HTML
        window.location.reload();
    }
    
    // Start auto-refresh every 30 seconds
    refreshInterval = setInterval(refreshDisplayData, 30000);
    
    // Clean up on page unload
    window.addEventListener('beforeunload', function() {
        if (refreshInterval) {
            clearInterval(refreshInterval);
        }
    });
</script>
@endpush
