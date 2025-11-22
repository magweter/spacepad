@extends('layouts.blank')
@section('page')
    <div id="panel-container" class="min-h-screen bg-gray-900" data-panel-id="{{ $panel->id }}" data-display-mode="{{ $panel->display_mode->value }}">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div id="loading" class="text-white text-center">
                <div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-white mb-4"></div>
                <p>Loading panel...</p>
            </div>
        </div>

        <div id="panel-content" class="hidden">
            @if($panel->display_mode->value === 'horizontal')
                <div id="horizontal-layout" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 p-4 min-h-screen">
                    <!-- Displays will be rendered here by JavaScript -->
                </div>
            @else
                <div id="availability-layout" class="p-4 min-h-screen">
                    <!-- Availability grid will be rendered here by JavaScript -->
                </div>
            @endif
        </div>
    </div>

    <style>
        .display-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .status-available {
            background-color: #10b981;
            color: white;
        }

        .status-reserved {
            background-color: #dc2626;
            color: white;
        }

        .status-transitioning {
            background-color: #f59e0b;
            color: white;
        }

        .status-checkin {
            background-color: #f59e0b;
            color: white;
        }

        .availability-grid {
            display: grid;
            gap: 2px;
        }

        .time-slot {
            padding: 8px;
            text-align: center;
            font-size: 12px;
        }
    </style>

    <script>
        const panelId = '{{ $panel->id }}';
        const displayMode = '{{ $panel->display_mode->value }}';
        const apiUrl = '/panels/' + panelId + '/data';
        const refreshInterval = 60000; // 60 seconds

        let refreshTimer;

        async function fetchPanelData() {
            try {
                const response = await fetch(apiUrl, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin'
                });

                if (!response.ok) {
                    throw new Error('Failed to fetch panel data');
                }

                const data = await response.json();
                renderPanel(data.data);
            } catch (error) {
                console.error('Error fetching panel data:', error);
                document.getElementById('loading').innerHTML = '<p class="text-red-500">Error loading panel. Please refresh the page.</p>';
            }
        }

        function renderPanel(data) {
            const loadingEl = document.getElementById('loading');
            const contentEl = document.getElementById('panel-content');
            
            loadingEl.classList.add('hidden');
            contentEl.classList.remove('hidden');

            if (displayMode === 'horizontal') {
                renderHorizontalLayout(data);
            } else {
                renderAvailabilityLayout(data);
            }
        }

        function renderHorizontalLayout(data) {
            const container = document.getElementById('horizontal-layout');
            container.innerHTML = '';

            data.displays.forEach((displayData, index) => {
                const display = displayData.display;
                const events = displayData.events || [];
                
                const currentEvent = getCurrentEvent(events);
                const nextEvent = getNextEvent(events);
                const status = getStatus(currentEvent, nextEvent);

                const card = document.createElement('div');
                card.className = 'display-card';
                card.innerHTML = `
                    <div class="mb-4">
                        <h3 class="text-xl font-bold text-gray-900">${escapeHtml(display.name)}</h3>
                    </div>
                    <div class="mb-4">
                        <div class="inline-block px-4 py-2 rounded-lg ${getStatusClass(status)}">
                            <span class="font-semibold">${getStatusText(status, currentEvent, nextEvent)}</span>
                        </div>
                    </div>
                    ${currentEvent ? `
                        <div class="mb-2">
                            <p class="text-sm text-gray-600">Current:</p>
                            <p class="text-lg font-semibold text-gray-900">${escapeHtml(currentEvent.summary)}</p>
                            <p class="text-sm text-gray-500">${formatTime(currentEvent.start)} - ${formatTime(currentEvent.end)}</p>
                        </div>
                    ` : ''}
                    ${nextEvent ? `
                        <div>
                            <p class="text-sm text-gray-600">Next:</p>
                            <p class="text-base font-medium text-gray-900">${escapeHtml(nextEvent.summary)}</p>
                            <p class="text-sm text-gray-500">${formatTime(nextEvent.start)}</p>
                        </div>
                    ` : ''}
                `;
                container.appendChild(card);
            });
        }

        function renderAvailabilityLayout(data) {
            const container = document.getElementById('availability-layout');
            // Simplified availability view - can be enhanced later
            container.innerHTML = '<div class="text-white text-center"><p>Availability view coming soon</p></div>';
        }

        function getCurrentEvent(events) {
            const now = new Date();
            return events.find(e => {
                const start = new Date(e.start);
                const end = new Date(e.end);
                return now >= start && now < end && e.status !== 'cancelled';
            });
        }

        function getNextEvent(events) {
            const now = new Date();
            return events
                .filter(e => {
                    const start = new Date(e.start);
                    return start > now && e.status !== 'cancelled';
                })
                .sort((a, b) => new Date(a.start) - new Date(b.start))[0];
        }

        function getStatus(currentEvent, nextEvent) {
            if (currentEvent) return 'reserved';
            if (nextEvent) {
                const minutesUntil = (new Date(nextEvent.start) - new Date()) / 60000;
                if (minutesUntil < 10) return 'transitioning';
            }
            return 'available';
        }

        function getStatusClass(status) {
            const classes = {
                'available': 'status-available',
                'reserved': 'status-reserved',
                'transitioning': 'status-transitioning',
                'checkin': 'status-checkin'
            };
            return classes[status] || 'status-available';
        }

        function getStatusText(status, currentEvent, nextEvent) {
            if (status === 'reserved' && currentEvent) {
                return 'Reserved';
            }
            if (status === 'transitioning') {
                return 'Starting Soon';
            }
            return 'Available';
        }

        function formatTime(dateString) {
            const date = new Date(dateString);
            return date.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function startAutoRefresh() {
            fetchPanelData();
            refreshTimer = setInterval(fetchPanelData, refreshInterval);
        }

        // Initialize
        startAutoRefresh();

        // Cleanup on page unload
        window.addEventListener('beforeunload', () => {
            if (refreshTimer) {
                clearInterval(refreshTimer);
            }
        });
    </script>
@endsection

