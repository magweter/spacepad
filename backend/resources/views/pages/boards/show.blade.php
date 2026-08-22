@extends('layouts.blank')
@section('title', $board->name . ' - ' . config('app.name'))
@php
    $theme = $board->theme ?? 'dark';
    // For system theme, don't add initial class - let JavaScript handle it to prevent flash
    // For dark/light themes, we can add the class directly since we know it's correct
    $initialThemeClass = $theme === 'system' ? '' : 'board-' . $theme;
    
    // Map font names to Google Fonts format
    $fontFamily = $board->font_family ?? 'Inter';
    $googleFontMap = [
        'Inter' => 'Inter:wght@400;500;600;700',
        'Roboto' => 'Roboto:wght@400;500;700',
        'Open Sans' => 'Open+Sans:wght@400;600;700',
        'Lato' => 'Lato:wght@400;700',
        'Poppins' => 'Poppins:wght@400;600;700',
        'Montserrat' => 'Montserrat:wght@400;600;700',
    ];
    $googleFontUrl = $googleFontMap[$fontFamily] ?? 'Inter:wght@400;500;600;700';
    
    // Set locale for translations
    $boardLanguage = $board->language ?? 'en';
    $originalLocale = app()->getLocale();
    app()->setLocale($boardLanguage);
    
    // Helper function to get translation in board language
    $t = function($key, $replace = []) use ($boardLanguage) {
        return \Illuminate\Support\Facades\Lang::get($key, $replace, $boardLanguage);
    };
@endphp

@push('styles')
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family={{ $googleFontUrl }}&display=swap" rel="stylesheet">
<script>
    // Set theme immediately to prevent flash - runs synchronously before page renders
    (function() {
        const theme = '{{ $theme }}';
        const isSystem = theme === 'system';
        let actualTheme = theme;
        
        if (isSystem && typeof window !== 'undefined' && window.matchMedia) {
            actualTheme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        }
        
        // Store for immediate use
        window.__boardInitialTheme = actualTheme;
        
        // Apply theme as soon as container exists
        const applyTheme = function() {
            const container = document.getElementById('board-container');
            if (container) {
                if (actualTheme === 'light') {
                    container.classList.remove('board-dark');
                    container.classList.add('board-light');
                } else {
                    container.classList.remove('board-light');
                    container.classList.add('board-dark');
                }
            }
        };
        
        // Try immediately
        if (document.readyState === 'loading') {
            // DOM is still loading, wait for it
            if (document.addEventListener) {
                document.addEventListener('DOMContentLoaded', applyTheme);
            }
        } else {
            // DOM already loaded
            applyTheme();
        }
        
        // Also try immediately in case container already exists
        setTimeout(applyTheme, 0);
    })();
</script>
<style>
    /* Prevent flash by hiding container until theme is applied */
    #board-container:not(.board-dark):not(.board-light) {
        opacity: 0;
    }
    #board-container.board-dark,
    #board-container.board-light {
        opacity: 1;
        transition: opacity 0.1s ease;
    }
    
    /* Dark mode (default) */
    .board-dark {
        background-color: #0f172a;
        color: #ffffff;
    }
    .board-dark .board-card {
        background: linear-gradient(135deg, #1e293b 0%, #1e293b 100%);
        border: 1px solid rgba(255, 255, 255, 0.05);
    }
    .board-dark .board-card:hover {
        background: linear-gradient(135deg, #334155 0%, #1e293b 100%);
        border-color: rgba(255, 255, 255, 0.1);
    }
    .board-dark .board-text-primary {
        color: #f1f5f9;
    }
    .board-dark .board-text-secondary {
        color: #94a3b8;
    }
    .board-dark .board-text-tertiary {
        color: #64748b;
    }
    .board-dark .board-text-accent {
        color: #fbbf24;
    }
    .board-dark .board-icon {
        color: #475569;
    }
    .board-dark .board-button {
        background-color: #1e293b;
    }
    .board-dark .board-button:hover {
        background-color: #334155;
    }

    /* Light mode */
    .board-light {
        background-color: #f8fafc;
        color: #0f172a;
    }
    .board-light .board-card {
        background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
        border: 1px solid rgba(0, 0, 0, 0.08);
    }
    .board-light .board-card:hover {
        background: linear-gradient(135deg, #ffffff 0%, #f1f5f9 100%);
        border-color: rgba(0, 0, 0, 0.12);
    }
    .board-light .board-text-primary {
        color: #0f172a;
    }
    .board-light .board-text-secondary {
        color: #475569;
    }
    .board-light .board-text-tertiary {
        color: #64748b;
    }
    .board-light .board-text-accent {
        color: #d97706;
    }
    .board-light .board-icon {
        color: #cbd5e1;
    }
    .board-light .board-button {
        background-color: #ffffff;
        border: 1px solid rgba(0, 0, 0, 0.1);
    }
    .board-light .board-button:hover {
        background-color: #f1f5f9;
    }
    
    /* Border styling */
    .board-dark .board-border {
        border-color: rgba(255, 255, 255, 0.1);
    }
    .board-light .board-border {
        border-color: rgba(0, 0, 0, 0.1);
    }
    
    /* Modern card styling */
    .board-card {
        backdrop-filter: blur(10px);
    }
    
    /* Smooth transitions */
    .board-card * {
        transition: color 0.2s ease, background-color 0.2s ease;
    }
    
    /* Status bar hover effect */
    .board-card:hover .absolute.left-0 {
        opacity: 0.9;
    }
</style>
@endpush

@section('page')
<div class="min-h-screen bg-gray-900 text-white p-8 {{ $initialThemeClass }}" id="board-container" data-theme="{{ $theme }}" style="font-family: '{{ $fontFamily }}', sans-serif;" data-language="{{ $board->language ?? 'en' }}">
    {{-- Header --}}
    <div class="flex items-center justify-between mb-10 pb-8 border-b border-white/10 board-border">
        <div class="flex items-center gap-5">
            @if($board->logo)
                <div class="flex-shrink-0">
                    <img src="{{ $board->is_public && $board->public_token ? route('boards.public.logo', $board->public_token) : route('boards.images.logo', $board) }}?v={{ $board->updated_at->timestamp }}" alt="Board logo" class="h-14 w-auto object-contain">
                </div>
            @else
                <div class="h-14 w-14 rounded-xl bg-gradient-to-br from-blue-500 to-blue-600 flex items-center justify-center text-white font-bold text-xl">
                    {{ strtoupper(substr($board->name, 0, 1)) }}
                </div>
            @endif
            <div>
                <h1 class="text-3xl font-bold tracking-tight board-text-primary">{{ $board->title ?: $t('boards.meeting_room_overview') }}</h1>
                @if($board->subtitle)
                    <p class="text-base mt-1.5 board-text-secondary font-medium">{{ $board->subtitle }}</p>
                @endif
            </div>
        </div>
        <div class="text-right">
            <div id="current-time" class="text-3xl font-mono font-bold board-text-primary tracking-tight"></div>
            <div id="current-date" class="text-sm mt-2 board-text-tertiary font-medium"></div>
        </div>
    </div>

    {{-- Room List --}}
    @php
        $viewMode = $board->view_mode ?? 'card';
        // $groups comes from Board::groupDisplayData(): ordered per the board's category config,
        // with the ungrouped catch-all (name === null) last. Only show headers when the board
        // actually has named categories.
        $hasCategories = $groups->contains(fn($group) => $group['name'] !== null);
    @endphp

    @if($viewMode === 'table')
        {{-- Row View --}}
        @php
            // Columns: Room | Status | Current | [Next]. The organizer rides along in the Current
            // and Next cells, on the meta line next to the time, rather than taking its own column.
            $showOrganizer = $board->show_booker ?? true;
            $showNextColumn = $board->show_next_event ?? true;
            $columnCount = 3 + ($showNextColumn ? 1 : 0);
            $totalDisplays = $groups->sum(fn($group) => $group['displays']->count());
        @endphp
        <div class="overflow-x-auto" id="displays-list">
            {{-- All groups share one table, so the browser sizes the columns across every row and
                 the columns line up between categories. Each group is its own tbody and repeats
                 the header row. --}}
            <table class="w-full border-collapse">
            @foreach($groups as $group)
                @php $categoryDisplays = $group['displays']; @endphp
                <tbody>
                    @if($hasCategories)
                        <tr>
                            <td colspan="{{ $columnCount }}" class="{{ $loop->first ? 'pb-2' : 'pt-8 pb-2' }}">
                                <x-boards.category-header :first="true"
                                                          :label="$group['name'] ?? $t('boards.uncategorised')" />
                            </td>
                        </tr>
                    @endif
                    <tr class="border-b border-gray-700/30">
                        <th class="text-left py-3 px-4 text-sm font-semibold uppercase tracking-wider board-text-secondary">{{ $t('boards.room') }}</th>
                        <th class="text-left py-3 px-4 text-sm font-semibold uppercase tracking-wider board-text-secondary">{{ $t('boards.status') }}</th>
                        <th class="text-left py-3 px-4 text-sm font-semibold uppercase tracking-wider board-text-secondary">{{ $t('boards.current') }}</th>
                        @if($showNextColumn)
                            <th class="text-left py-3 px-4 text-sm font-semibold uppercase tracking-wider board-text-secondary">{{ $t('boards.next') }}</th>
                        @endif
                    </tr>
                    @foreach($categoryDisplays as $displayData)
                        @php
                            $display = $displayData['display'];
                            $status = $displayData['status'];
                            $statusText = $displayData['statusText'];
                            $currentEvent = $displayData['currentEvent'];
                            $nextEvent = $displayData['nextEvent'];
                            $transitioningMinutes = $displayData['transitioningMinutes'] ?? null;

                            if ($status === 'transitioning' && $transitioningMinutes !== null) {
                                $statusText = $t('boards.transitioning_minutes', ['minutes' => $transitioningMinutes]);
                            }

                            $statusBadgeClass = match($status) {
                                'busy' => 'bg-red-500/10 text-red-400 border-red-500/20',
                                'transitioning', 'check_in' => 'bg-amber-500/10 text-amber-400 border-amber-500/20',
                                'error' => 'bg-gray-500/10 text-gray-400 border-gray-500/20',
                                default => 'bg-green-500/10 text-green-400 border-green-500/20',
                            };
                        @endphp
                        <tr class="border-b border-gray-700/20 hover:bg-gray-800/30 transition-colors">
                            <td class="py-3 px-4">
                                <div class="font-semibold text-base board-text-primary">{{ $display->display_name ?: $display->name }}</div>
                            </td>
                            <td class="py-3 px-4">
                                <span class="inline-flex items-center px-2 py-1 rounded text-xs font-semibold uppercase tracking-wider border {{ $statusBadgeClass }}">{{ $statusText }}</span>
                            </td>
                            <td class="py-3 px-4">
                                @if($currentEvent)
                                    <div class="space-y-1">
                                        @if($board->show_title ?? true)
                                            <div class="text-sm font-semibold board-text-primary">{{ $currentEvent['summary'] }}</div>
                                        @endif
                                        <div class="flex items-center gap-2 text-xs board-text-secondary">
                                            <div class="flex items-center gap-1.5">
                                                <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                </svg>
                                                <span class="event-time" data-start="{{ $currentEvent['start']->toIso8601String() }}" data-end="{{ $currentEvent['end']->toIso8601String() }}"></span>
                                            </div>
                                            @if($showOrganizer && filled($currentEvent['organizer']))
                                                <span class="text-gray-500">•</span>
                                                <div class="flex items-center gap-1.5">
                                                    <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                                    </svg>
                                                    <span>{{ $currentEvent['organizer'] }}</span>
                                                </div>
                                            @endif
                                        </div>
                                        @if(($board->show_join_button ?? false) && !empty($currentEvent['joinUrl']))
                                            <a href="{{ $currentEvent['joinUrl'] }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1 mt-0.5 px-2 py-0.5 rounded text-xs font-medium bg-blue-500/20 text-blue-400 border border-blue-500/30 hover:bg-blue-500/30 transition-colors">
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.069A1 1 0 0121 8.845v6.31a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path></svg>
                                                {{ $t('boards.join_meeting') }}
                                            </a>
                                        @endif
                                    </div>
                                @else
                                    @if($nextEvent)
                                        {{-- Both variants are siblings so scopeToToday() can swap them --}}
                                        <span class="text-sm board-text-secondary available-until" data-time="{{ $nextEvent['start']->toIso8601String() }}">
                                            {{ $t('boards.available_until', ['time' => '']) }}<span class="available-until-time" data-time="{{ $nextEvent['start']->toIso8601String() }}"></span>
                                        </span>
                                        <span class="text-sm board-text-secondary available-all-day" hidden>{{ $t('boards.available_until_end_of_day') }}</span>
                                    @else
                                        <span class="text-sm board-text-secondary">{{ $t('boards.available_until_end_of_day') }}</span>
                                    @endif
                                @endif
                            </td>
                            @if($showNextColumn)
                                <td class="py-3 px-4">
                                    @if($nextEvent)
                                        <div class="space-y-1 next-event" data-start="{{ $nextEvent['start']->toIso8601String() }}">
                                            @if($board->show_title ?? true)
                                                <div class="text-sm font-semibold board-text-primary">{{ $nextEvent['summary'] }}</div>
                                            @endif
                                            <div class="flex items-center gap-2 text-xs board-text-secondary">
                                                <div class="flex items-center gap-1.5">
                                                    <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                    </svg>
                                                    <span class="event-time" data-start="{{ $nextEvent['start']->toIso8601String() }}" data-end="{{ $nextEvent['end']->toIso8601String() }}"></span>
                                                </div>
                                                @if($showOrganizer && filled($nextEvent['organizer'] ?? null))
                                                    <span class="text-gray-500">•</span>
                                                    <div class="flex items-center gap-1.5">
                                                        <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                                        </svg>
                                                        <span>{{ $nextEvent['organizer'] }}</span>
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                        <span class="text-sm board-text-secondary next-event-empty" hidden>-</span>
                                    @else
                                        <span class="text-sm board-text-secondary">-</span>
                                    @endif
                                </td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            @endforeach
            @if($totalDisplays === 0)
                <tbody>
                    <tr>
                        <td colspan="{{ $columnCount }}" class="py-16 text-center">
                            <div class="flex flex-col items-center gap-4">
                                <div class="h-16 w-16 rounded-full bg-gray-500/20 flex items-center justify-center">
                                    <x-icons.display class="h-8 w-8 board-text-tertiary" />
                                </div>
                                <p class="text-lg board-text-secondary">{{ $t('boards.no_displays') }}</p>
                            </div>
                        </td>
                    </tr>
                </tbody>
            @endif
            </table>
        </div>
    @elseif($viewMode === 'grid')
        {{-- Grid View --}}
        <div id="displays-list">
            @foreach($groups as $group)
                @php $categoryDisplays = $group['displays']; @endphp
                @if($hasCategories)
                    <x-boards.category-header class="mb-4" :first="$loop->first"
                                              :label="$group['name'] ?? $t('boards.uncategorised')" />
                @endif
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 {{ $hasCategories && !$loop->last ? 'mb-2' : '' }}">
            @forelse($categoryDisplays as $displayData)
                @php
                    $display = $displayData['display'];
                    $status = $displayData['status'];
                    $statusText = $displayData['statusText'];
                    $currentEvent = $displayData['currentEvent'];
                    $nextEvent = $displayData['nextEvent'];
                    $transitioningMinutes = $displayData['transitioningMinutes'] ?? null;
                    
                    // Status colors
                    $statusBarColor = match($status) {
                        'busy' => 'bg-red-500',
                        'transitioning', 'check_in' => 'bg-amber-500',
                        'error' => 'bg-gray-500',
                        default => 'bg-green-500',
                    };
                    
                    // Update status text with minutes if transitioning
                    $statusTextParts = [];
                    if ($status === 'transitioning' && $transitioningMinutes !== null) {
                        $statusTextParts = [
                            'label' => $t('boards.transitioning'),
                            'minutes' => '(' . $transitioningMinutes . ' min)'
                        ];
                    } else {
                        $statusTextParts = ['label' => $statusText];
                    }
                @endphp
                <div class="board-card relative overflow-hidden rounded-xl transition-all duration-300 hover:scale-[1.01]">
                    {{-- Status Indicator Bar - Full height on left edge --}}
                    <div class="absolute left-0 top-0 bottom-0 w-1 {{ $statusBarColor }}"></div>
                    
                    <div class="pl-8 pr-6 py-5 flex flex-col gap-2">
                        {{-- Status Badge and Room Name --}}
                        <div>
                            {{-- Status Badge - Above Title --}}
                            <div class="mb-5">
                                @php
                                    $statusBadgeClass = match($status) {
                                        'busy' => 'bg-red-500/10 text-red-400 border-red-500/20',
                                        'transitioning' => 'bg-amber-500/10 text-amber-400 border-amber-500/20',
                                        'error' => 'bg-gray-500/10 text-gray-400 border-gray-500/20',
                                        default => 'bg-green-500/10 text-green-400 border-green-500/20',
                                    };
                                @endphp
                                <span class="inline-flex flex-col items-center justify-center px-3 py-1.5 rounded-lg text-xs font-semibold uppercase tracking-wider text-center border {{ $statusBadgeClass }}">
                                    @if(isset($statusTextParts['minutes']))
                                        <span>{{ $statusTextParts['label'] }}</span>
                                        <span>{{ $statusTextParts['minutes'] }}</span>
                                    @else
                                        <span>{{ $statusText }}</span>
                                    @endif
                                </span>
                            </div>
                            {{-- Room Name --}}
                            <h3 class="text-xl font-bold board-text-primary">{{ $display->display_name ?: $display->name }}</h3>
                        </div>
                        
                        {{-- Event Info --}}
                        <div>
                            @if($currentEvent)
                                {{-- Current Event --}}
                                <div class="space-y-1">
                                    @if($board->show_title ?? true)
                                        <div class="text-base font-semibold board-text-primary">{{ $currentEvent['summary'] }}</div>
                                    @endif
                                    <div class="flex items-center gap-3 text-sm board-text-secondary">
                                        <div class="flex items-center gap-1.5">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                            </svg>
                                            <span class="event-time" data-start="{{ $currentEvent['start']->toIso8601String() }}" data-end="{{ $currentEvent['end']->toIso8601String() }}"></span>
                                        </div>
                                        @if(($board->show_booker ?? true) && filled($currentEvent['organizer']))
                                            <span class="text-gray-500">•</span>
                                            <div class="flex items-center gap-1.5">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                                </svg>
                                                <span>{{ $currentEvent['organizer'] }}</span>
                                            </div>
                                        @endif
                                    </div>
                                    @if(($board->show_join_button ?? false) && !empty($currentEvent['joinUrl']))
                                        <a href="{{ $currentEvent['joinUrl'] }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1.5 mt-1 px-2.5 py-1 rounded text-xs font-medium bg-blue-500/20 text-blue-400 border border-blue-500/30 hover:bg-blue-500/30 transition-colors">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.069A1 1 0 0121 8.845v6.31a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path></svg>
                                            {{ $t('boards.join_meeting') }}
                                        </a>
                                    @endif
                                </div>
                            @else
                                {{-- Available --}}
                                @if($nextEvent)
                                    <span class="text-base font-medium board-text-secondary available-until" data-time="{{ $nextEvent['start']->toIso8601String() }}">
                                        {{ $t('boards.available_until', ['time' => '']) }}<span class="available-until-time" data-time="{{ $nextEvent['start']->toIso8601String() }}"></span>
                                    </span>
                                    <span class="text-base font-medium board-text-secondary available-all-day" hidden>{{ $t('boards.available_until_end_of_day') }}</span>
                                @else
                                    <span class="text-base font-medium board-text-secondary">{{ $t('boards.available_until_end_of_day') }}</span>
                                @endif
                            @endif

                            {{-- Next Up Event - Below Current Event --}}
                            @if($nextEvent && ($board->show_next_event ?? true))
                                <div class="pt-3 mt-3 border-t border-gray-700/30 next-event" data-start="{{ $nextEvent['start']->toIso8601String() }}">
                                    <div class="space-y-1">
                                        @if($board->show_title ?? true)
                                            <div class="flex items-center justify-between gap-2">
                                                <div class="text-base font-semibold board-text-primary truncate">{{ $nextEvent['summary'] }}</div>
                                                <span class="inline-flex items-center px-2 py-1 rounded-md text-xs font-medium bg-blue-500/10 text-blue-400 border border-blue-500/20">{{ $t('boards.next') }}</span>
                                            </div>
                                        @endif
                                        <div class="flex items-center gap-3 text-sm board-text-secondary">
                                            <div class="flex items-center gap-1.5">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                </svg>
                                                <span class="event-time" data-start="{{ $nextEvent['start']->toIso8601String() }}" data-end="{{ $nextEvent['end']->toIso8601String() }}"></span>
                                            </div>
                                            @if(($board->show_booker ?? true) && filled($nextEvent['organizer'] ?? null))
                                                <span class="text-gray-500">•</span>
                                                <div class="flex items-center gap-1.5">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                                    </svg>
                                                    <span>{{ $nextEvent['organizer'] }}</span>
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-span-full board-card rounded-xl p-16 text-center">
                    <div class="flex flex-col items-center gap-4">
                        <div class="h-16 w-16 rounded-full bg-gray-500/20 flex items-center justify-center">
                            <x-icons.display class="h-8 w-8 board-text-tertiary" />
                        </div>
                        <p class="text-lg font-medium board-text-secondary">{{ $t('boards.no_displays') }}</p>
                    </div>
                </div>
            @endforelse
            </div>
            @endforeach
        </div>
    @else
        {{-- Card View (Default) --}}
        <div id="displays-list">
            @foreach($groups as $group)
                @php $categoryDisplays = $group['displays']; @endphp
                @if($hasCategories)
                    <x-boards.category-header class="mb-4" :first="$loop->first"
                                              :label="$group['name'] ?? $t('boards.uncategorised')" />
                @endif
            <div class="space-y-4 {{ $hasCategories && !$loop->last ? 'mb-2' : '' }}">
            @forelse($categoryDisplays as $displayData)
                @php
                    $display = $displayData['display'];
                    $status = $displayData['status'];
                    $statusText = $displayData['statusText'];
                    $currentEvent = $displayData['currentEvent'];
                    $nextEvent = $displayData['nextEvent'];
                    $transitioningMinutes = $displayData['transitioningMinutes'] ?? null;
                    
                    // Status colors
                    $statusBarColor = match($status) {
                        'busy' => 'bg-red-500',
                        'transitioning', 'check_in' => 'bg-amber-500',
                        'error' => 'bg-gray-500',
                        default => 'bg-green-500',
                    };
                    
                    // Update status text with minutes if transitioning
                    $statusTextParts = [];
                    if ($status === 'transitioning' && $transitioningMinutes !== null) {
                        $statusTextParts = [
                            'label' => $t('boards.transitioning'),
                            'minutes' => '(' . $transitioningMinutes . ' min)'
                        ];
                    } else {
                        $statusTextParts = ['label' => $statusText];
                    }
                @endphp
                <div class="board-card relative overflow-hidden rounded-xl transition-all duration-300 hover:scale-[1.01]">
                {{-- Status Indicator Bar - Full height on left edge --}}
                <div class="absolute left-0 top-0 bottom-0 w-1 {{ $statusBarColor }}"></div>
                
                <div class="pl-8 pr-6 py-4.5 flex items-center gap-6">
                    {{-- Status Badge --}}
                    <div class="flex-shrink-0 w-36 flex justify-center">
                        @php
                            $statusBadgeClass = match($status) {
                                'busy' => 'bg-red-500/10 text-red-400 border-red-500/20',
                                'transitioning', 'check_in' => 'bg-amber-500/10 text-amber-400 border-amber-500/20',
                                'error' => 'bg-gray-500/10 text-gray-400 border-gray-500/20',
                                default => 'bg-green-500/10 text-green-400 border-green-500/20',
                            };
                        @endphp
                        <span class="inline-flex flex-col items-center justify-center px-3 py-1.5 rounded-lg text-xs font-semibold uppercase tracking-wider text-center border w-full {{ $statusBadgeClass }}">
                            @if(isset($statusTextParts['minutes']))
                                <span>{{ $statusTextParts['label'] }}</span>
                                <span>{{ $statusTextParts['minutes'] }}</span>
                            @else
                                <span>{{ $statusText }}</span>
                            @endif
                        </span>
                    </div>
                    
                    {{-- Room Name and Current Event Info --}}
                    <div class="flex-1 min-w-0 pl-2">
                        <h3 class="text-xl font-bold mb-2 board-text-primary">{{ $display->display_name ?: $display->name }}</h3>
                        
                        @if($currentEvent)
                            {{-- Current Event --}}
                            <div class="space-y-1">
                                @if($board->show_title ?? true)
                                    <div class="text-base font-semibold board-text-primary truncate">{{ $currentEvent['summary'] }}</div>
                                @endif
                                <div class="flex items-center gap-3 text-sm board-text-secondary">
                                    <div class="flex items-center gap-1.5">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                        <span class="event-time" data-start="{{ $currentEvent['start']->toIso8601String() }}" data-end="{{ $currentEvent['end']->toIso8601String() }}"></span>
                                    </div>
                                    @if(($board->show_booker ?? true) && filled($currentEvent['organizer']))
                                        <span class="text-gray-500">•</span>
                                        <div class="flex items-center gap-1.5">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                            </svg>
                                            <span>{{ $currentEvent['organizer'] }}</span>
                                        </div>
                                    @endif
                                </div>
                                @if(($board->show_join_button ?? false) && !empty($currentEvent['joinUrl']))
                                    <a href="{{ $currentEvent['joinUrl'] }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1.5 mt-1 px-2.5 py-1 rounded text-xs font-medium bg-blue-500/20 text-blue-400 border border-blue-500/30 hover:bg-blue-500/30 transition-colors">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.069A1 1 0 0121 8.845v6.31a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path></svg>
                                        {{ $t('boards.join_meeting') }}
                                    </a>
                                @endif
                            </div>
                        @else
                            {{-- Available --}}
                            @if($nextEvent)
                                <span class="text-base font-medium board-text-secondary available-until" data-time="{{ $nextEvent['start']->toIso8601String() }}">
                                    {{ $t('boards.available_until', ['time' => '']) }}<span class="available-until-time" data-time="{{ $nextEvent['start']->toIso8601String() }}"></span>
                                </span>
                                <span class="text-base font-medium board-text-secondary available-all-day" hidden>{{ $t('boards.available_until_end_of_day') }}</span>
                            @else
                                <span class="text-base font-medium board-text-secondary">{{ $t('boards.available_until_end_of_day') }}</span>
                            @endif
                        @endif
                    </div>

                    {{-- Next Up Event - Right Side --}}
                    @if($nextEvent && ($board->show_next_event ?? true))
                        <div class="flex-shrink-0 text-right ml-6 next-event" data-start="{{ $nextEvent['start']->toIso8601String() }}">
                            <div class="space-y-2">
                                <div class="flex items-center justify-end gap-2">
                                    <span class="inline-flex items-center px-2 py-1 rounded-md text-xs font-medium bg-blue-500/10 text-blue-400 border border-blue-500/20">{{ $t('boards.next') }}</span>
                                </div>
                                <div class="space-y-1">
                                    @if($board->show_title ?? true)
                                        <div class="text-base font-semibold board-text-primary truncate">{{ $nextEvent['summary'] }}</div>
                                    @endif
                                    <div class="flex items-center justify-end gap-3 text-sm board-text-secondary">
                                        <div class="flex items-center gap-1.5">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                            </svg>
                                            <span class="event-time" data-start="{{ $nextEvent['start']->toIso8601String() }}" data-end="{{ $nextEvent['end']->toIso8601String() }}"></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
            @empty
                <div class="board-card rounded-xl p-16 text-center">
                    <div class="flex flex-col items-center gap-4">
                        <div class="h-16 w-16 rounded-full bg-gray-500/20 flex items-center justify-center">
                            <x-icons.display class="h-8 w-8 board-text-tertiary" />
                        </div>
                        <p class="text-lg font-medium board-text-secondary">{{ $t('boards.no_displays') }}</p>
                    </div>
                </div>
            @endforelse
            </div>
            @endforeach
        </div>
    @endif
</div>
@endsection

@php
    // Restore original locale
    app()->setLocale($originalLocale);
@endphp

@push('scripts')
<script>
    // Theme management - use theme from database
    function getSystemTheme() {
        return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    }

    function setTheme(theme) {
        const container = document.getElementById('board-container');
        if (!container) return;
        
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

    // Initialize theme - use pre-calculated theme from inline script if available
    function initializeTheme() {
        const container = document.getElementById('board-container');
        if (!container) return;
        
        const theme = container.dataset.theme || 'dark';
        
        // Use pre-calculated theme if available (from inline script in head)
        const initialTheme = window.__boardInitialTheme;
        if (initialTheme) {
            setTheme(initialTheme);
        } else {
            setTheme(theme === 'system' ? getSystemTheme() : theme);
        }
        
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
    }

    // Run immediately
    initializeTheme();

    // Also run on DOMContentLoaded as fallback
    document.addEventListener('DOMContentLoaded', initializeTheme);

        // Get board language from data attribute
        const boardLanguage = document.getElementById('board-container')?.dataset.language || 'en';
        
        // Format time using board language (without seconds)
        function formatTime(date) {
            return date.toLocaleTimeString(boardLanguage, { 
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

        // Format date using board language
        function formatDate(date) {
            return date.toLocaleDateString(boardLanguage, {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
        }

    // Update current time every second
    function updateTime() {
        const now = new Date();
        // Format current time with seconds for the clock display, using the board's language
        // so English boards show 12-hour (12:31 AM) and other locales use their native format
        const timeString = now.toLocaleTimeString(boardLanguage, {
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
    
    // Format all "available until" times
    function formatAvailableUntilTimes() {
        document.querySelectorAll('.available-until-time').forEach(element => {
            const time = new Date(element.dataset.time);
            element.textContent = formatTime(time);
        });
    }
    
    function isSameLocalDay(a, b) {
        return a.getFullYear() === b.getFullYear()
            && a.getMonth() === b.getMonth()
            && a.getDate() === b.getDate();
    }

    // Only surface bookings that fall on the viewer's current day. A bare "9:00 - 11:30" next
    // to a running meeting reads as if both are happening now, so a next-up event on a later
    // day is dropped and the room simply counts as free for the rest of today.
    // This has to happen client-side: every time on this board is rendered in the browser's
    // timezone, so a server-side (UTC) day boundary would disagree with what is on screen.
    // See EventService::getAllEvents — it deliberately returns a wide yesterday..tomorrow
    // window and leaves clamping to the local day to the client.
    function scopeToToday() {
        const now = new Date();

        document.querySelectorAll('.next-event').forEach(element => {
            if (isSameLocalDay(new Date(element.dataset.start), now)) return;
            element.hidden = true;
            const placeholder = element.parentElement?.querySelector('.next-event-empty');
            if (placeholder) placeholder.hidden = false;
        });

        document.querySelectorAll('.available-until').forEach(element => {
            if (isSameLocalDay(new Date(element.dataset.time), now)) return;
            element.hidden = true;
            const allDay = element.parentElement?.querySelector('.available-all-day');
            if (allDay) allDay.hidden = false;
        });
    }

    // Initialize on page load
    updateTime();
    scopeToToday();
    formatEventTimes();
    formatAvailableUntilTimes();
    
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
