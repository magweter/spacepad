@php
$faqs = [
    [
        'id' => 1,
        'category' => 'setup',
        'categoryLabel' => 'Getting started',
        'accentColor' => '#3b82f6',
        'question' => 'How do I connect my first display?',
        'answer' => 'Download the Spacepad app on your tablet from the Play Store or App Store. Open it and enter the connect code shown at the top right of this dashboard (or click "How to connect a tablet"). Your display will appear in the list above once connected.',
    ],
    [
        'id' => 2,
        'category' => 'setup',
        'categoryLabel' => 'Getting started',
        'accentColor' => '#3b82f6',
        'question' => 'Where do I find my connect code?',
        'answer' => 'It\'s shown in the top right of this page once you have a display — or hit the "How to connect a tablet" button. Each code is unique to your account. If you don\'t see it, create a display first using the button above.',
    ],
    [
        'id' => 3,
        'category' => 'm365',
        'categoryLabel' => 'Microsoft 365',
        'accentColor' => '#8b5cf6',
        'question' => 'My display shows the organizer\'s name instead of the meeting title',
        'answer' => 'This is an Exchange Online setting on your room mailbox — not a Spacepad issue. Fix it via PowerShell: Set-CalendarProcessing -Identity "room@yourdomain.com" -AddOrganizerToSubject $false -DeleteSubject $false. You\'ll need Exchange admin access to run this command.',
    ],
    [
        'id' => 4,
        'category' => 'm365',
        'categoryLabel' => 'Microsoft 365',
        'accentColor' => '#8b5cf6',
        'question' => 'New meetings aren\'t showing up on my display',
        'answer' => 'First, check that the right room or calendar is connected (open display settings). If it looks correct, try disconnecting and reconnecting your Microsoft account — this refreshes the webhook that notifies Spacepad of changes in real time. Events should appear within a few minutes.',
    ],
    [
        'id' => 5,
        'category' => 'm365',
        'categoryLabel' => 'Microsoft 365',
        'accentColor' => '#8b5cf6',
        'question' => 'Our IT admin needs to approve Spacepad\'s Microsoft 365 permissions',
        'answer' => 'For write (booking) access to M365 room resources, your Azure AD admin needs to grant admin consent for the Spacepad app. A link to the admin consent flow is shown during the Microsoft connection process — you can copy it and forward it to your admin. For read-only access, regular user consent is usually enough.',
    ],
    [
        'id' => 6,
        'category' => 'google',
        'categoryLabel' => 'Google',
        'accentColor' => '#ef4444',
        'question' => 'My Google Workspace room calendars aren\'t syncing',
        'answer' => 'Make sure you connected a Google Workspace account (not a personal Gmail) and granted write permission during the connection flow. Also check that the room resources are accessible by the connected account. If you\'re using a service account, verify domain-wide delegation is configured in your Google Admin console.',
    ],
    [
        'id' => 7,
        'category' => 'google',
        'categoryLabel' => 'Google',
        'accentColor' => '#ef4444',
        'question' => 'When do I need a service account for Google Workspace?',
        'answer' => 'A service account is the right choice when your Google Workspace admin doesn\'t want to grant a personal account access to room resources, or when you\'re managing many rooms at scale. It uses domain-wide delegation to access calendars on behalf of users. If you\'re unsure, start with a regular user account — you can switch later without losing your display setup.',
    ],
    [
        'id' => 8,
        'category' => 'display',
        'categoryLabel' => 'Display',
        'accentColor' => '#10b981',
        'question' => 'My display shows "No upcoming events" but meetings exist',
        'answer' => 'A few common causes: the connected calendar differs from the one actually used to book the room; the first sync can take a few minutes after initial setup; or the account connection may have expired. Check that the Accounts panel shows "Connected" and try pulling to refresh on the display app.',
    ],
    [
        'id' => 9,
        'category' => 'display',
        'categoryLabel' => 'Display',
        'accentColor' => '#10b981',
        'question' => 'Can I book a room directly from the display tablet?',
        'answer' => 'Yes — on-display booking is a Pro feature and requires a connected account with write permissions. Once both are active, a booking button appears on the display. You can configure the default booking duration and toggle the feature per display in its settings.',
    ],
    [
        'id' => 10,
        'category' => 'display',
        'categoryLabel' => 'Display',
        'accentColor' => '#10b981',
        'question' => 'My tablet keeps going to sleep or showing a screensaver',
        'answer' => 'This is a tablet OS setting, not Spacepad. On Android: set screen timeout to "Never" and disable adaptive brightness. On iPad: go to Settings → Display & Brightness → Auto-Lock → Never. For permanent kiosk use, also look into Android\'s guided access or a dedicated kiosk launcher app to keep the display locked to Spacepad.',
    ],
    [
        'id' => 11,
        'category' => 'billing',
        'categoryLabel' => 'Billing',
        'accentColor' => '#f59e0b',
        'question' => 'What\'s included in the free plan?',
        'answer' => 'The free plan includes 1 display with real-time calendar sync and basic event viewing — enough to fully test with your first room. Booking, multiple displays, boards, check-in, and display customization all require Pro.',
    ],
    [
        'id' => 12,
        'category' => 'billing',
        'categoryLabel' => 'Billing',
        'accentColor' => '#f59e0b',
        'question' => 'What\'s included in Pro?',
        'answer' => 'Pro unlocks unlimited displays, meeting boards (multi-room overview screens for lobbies or hallway TVs), on-display room booking, check-in functionality, full display customization (logo, colors, themes), and priority support. See spacepad.io/pricing for full details.',
    ],
    [
        'id' => 13,
        'category' => 'billing',
        'categoryLabel' => 'Billing',
        'accentColor' => '#f59e0b',
        'question' => 'What happens when my trial ends?',
        'answer' => 'Your account reverts to the free plan — no data is lost and nothing is deleted. Your first display keeps working. Any additional displays will show a "subscription required" message until you upgrade. You can upgrade any time from this dashboard.',
    ],
];

$categories = [
    ['id' => 'setup',   'label' => 'Getting started', 'color' => '#3b82f6'],
    ['id' => 'm365',    'label' => 'Microsoft 365',   'color' => '#8b5cf6'],
    ['id' => 'google',  'label' => 'Google',           'color' => '#ef4444'],
    ['id' => 'display', 'label' => 'Display',          'color' => '#10b981'],
    ['id' => 'billing', 'label' => 'Billing',          'color' => '#f59e0b'],
];

$userVotedIds = \App\Models\RoadmapVote::where('user_id', auth()->id())
    ->pluck('roadmap_item_id')
    ->toArray();

$roadmapItems = \App\Models\RoadmapItem::approved()
    ->ordered()
    ->withCount('votes')
    ->get()
    ->map(fn($item) => [
        'id'          => $item->id,
        'title'       => $item->title,
        'description' => $item->description,
        'status'      => $item->status->value,
        'statusLabel' => $item->status->label(),
        'statusClass' => $item->status->badgeClass(),
        'isPulse'     => $item->status->value === 'building',
        'expectedAt'  => $item->expected_at?->format('M Y'),
        'votesCount'  => $item->votes_count,
        'voted'       => in_array($item->id, $userVotedIds),
    ])
    ->values()
    ->all();
@endphp

@push('scripts')
<script>
    function faqHelp() {
        return {
            open: {{ session('support_sent') || session('suggestion_sent') ? 'true' : 'false' }},
            activeTab: '{{ session('suggestion_sent') ? 'roadmap' : 'help' }}',
            search: '',
            activeCategory: 'all',
            openId: null,
            showAll: false,
            faqs: @json($faqs),
            roadmapItems: @json($roadmapItems),
            init() {
                window.addEventListener('open-faq', (e) => {
                    this.open = true;
                    if (e.detail?.tab) this.activeTab = e.detail.tab;
                });
            },
            get allMatching() {
                return this.faqs.filter(faq => {
                    const matchCat = this.activeCategory === 'all' || faq.category === this.activeCategory;
                    const s = this.search.toLowerCase();
                    return matchCat && (s === '' || faq.question.toLowerCase().includes(s) || faq.answer.toLowerCase().includes(s));
                });
            },
            get filtered() {
                const all = this.allMatching;
                return (this.showAll || this.search !== '' || this.activeCategory !== 'all')
                    ? all
                    : all.slice(0, 4);
            },
            get hasMore() {
                return !this.showAll && this.search === '' && this.activeCategory === 'all' && this.allMatching.length > 4;
            },
            toggle(id) {
                this.openId = this.openId === id ? null : id;
            },
            vote(item) {
                const wasVoted = item.voted;
                item.voted = !wasVoted;
                item.votesCount += wasVoted ? -1 : 1;
                fetch(`/roadmap/${item.id}/vote`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                })
                .then(r => r.json())
                .then(data => {
                    item.voted = data.voted;
                    item.votesCount = data.votes_count;
                })
                .catch(() => {
                    item.voted = wasVoted;
                    item.votesCount += wasVoted ? 1 : -1;
                });
            },
            close() {
                this.open = false;
            }
        };
    }
</script>
@endpush

<div x-data="faqHelp()">

    {{-- Backdrop --}}
    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        @click="close()"
        class="fixed inset-0 bg-black/25 z-40"
        style="display: none"
    ></div>

    {{-- Slide-over panel --}}
    <div
        x-show="open"
        x-transition:enter="transform transition ease-in-out duration-300"
        x-transition:enter-start="translate-x-full"
        x-transition:enter-end="translate-x-0"
        x-transition:leave="transform transition ease-in-out duration-200"
        x-transition:leave-start="translate-x-0"
        x-transition:leave-end="translate-x-full"
        class="fixed inset-y-0 right-0 z-50 flex w-full flex-col bg-white shadow-2xl sm:max-w-2xl"
        style="display: none"
        @keydown.escape.window="close()"
    >
        {{-- Panel header --}}
        <div class="flex-shrink-0 border-b border-gray-100">
            <div class="flex items-center justify-between gap-4 px-6 pt-4 pb-0">
                <h2 class="text-base font-semibold text-gray-900">Help & Roadmap</h2>
                <button @click="close()" type="button" class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition-colors">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            {{-- Tab bar --}}
            <nav class="flex gap-1 px-6 mt-3">
                <button
                    @click="activeTab = 'help'"
                    type="button"
                    :class="activeTab === 'help' ? 'border-violet-600 text-violet-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
                    class="border-b-2 pb-3 pr-4 text-sm font-medium transition-colors whitespace-nowrap"
                >FAQ & Help</button>
                <button
                    @click="activeTab = 'roadmap'"
                    type="button"
                    :class="activeTab === 'roadmap' ? 'border-violet-600 text-violet-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
                    class="border-b-2 pb-3 px-4 text-sm font-medium transition-colors whitespace-nowrap"
                >
                    Roadmap & Ideas
                    <span x-show="roadmapItems.length > 0" class="ml-1.5 inline-flex items-center rounded-full bg-gray-100 px-1.5 py-0.5 text-xs font-medium text-gray-600" x-text="roadmapItems.length"></span>
                </button>
            </nav>
        </div>

        {{-- Search + category filters (sticky, non-scrolling) — help tab only --}}
        <div x-show="activeTab === 'help'" class="flex-shrink-0 border-b border-gray-100 px-6 py-4 space-y-3">
            <div class="relative">
                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                    <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                </div>
                <input
                    type="search"
                    x-model="search"
                    @input="openId = null"
                    placeholder="Search questions..."
                    class="block w-full rounded-lg border border-gray-200 bg-white py-2 pl-9 pr-3 text-sm text-gray-900 placeholder-gray-400 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                />
            </div>
            <div class="flex gap-2 overflow-x-auto pb-0.5 scrollbar-none">
                <button
                    @click="activeCategory = 'all'; openId = null"
                    :class="activeCategory === 'all' ? 'bg-gray-900 text-white border-gray-900' : 'bg-white text-gray-600 border-gray-200 hover:border-gray-400'"
                    class="inline-flex flex-shrink-0 items-center rounded-full border px-3.5 py-1.5 text-xs font-medium transition-all duration-150"
                    type="button"
                >All</button>
                @foreach($categories as $cat)
                <button
                    @click="activeCategory = '{{ $cat['id'] }}'; openId = null"
                    :style="activeCategory === '{{ $cat['id'] }}' ? 'background-color: {{ $cat['color'] }}; border-color: {{ $cat['color'] }}' : ''"
                    :class="activeCategory === '{{ $cat['id'] }}' ? 'text-white' : 'bg-white text-gray-600 border-gray-200 hover:border-gray-400'"
                    class="inline-flex flex-shrink-0 items-center gap-1.5 rounded-full border px-3.5 py-1.5 text-xs font-medium transition-all duration-150"
                    type="button"
                >
                    <span
                        class="h-1.5 w-1.5 flex-shrink-0 rounded-full"
                        :style="activeCategory === '{{ $cat['id'] }}' ? 'background-color: rgba(255,255,255,0.7)' : 'background-color: {{ $cat['color'] }}'"
                    ></span>
                    {{ $cat['label'] }}
                </button>
                @endforeach
            </div>
        </div>

        {{-- Scrollable content: Help tab --}}
        <div x-show="activeTab === 'help'" class="flex-1 overflow-y-auto px-6 py-5 space-y-2">

            {{-- FAQ accordion --}}
            <template x-for="faq in filtered" :key="faq.id">
                <div
                    class="rounded-xl border bg-white transition-all duration-150"
                    :class="openId === faq.id ? 'shadow-sm border-gray-300' : 'border-gray-200 hover:border-gray-300'"
                    :style="openId === faq.id ? 'border-left: 3px solid ' + faq.accentColor : 'border-left: 3px solid transparent'"
                >
                    <button
                        @click="toggle(faq.id)"
                        class="w-full flex items-start justify-between gap-4 px-5 py-4 text-left"
                        type="button"
                    >
                        <div class="flex items-start gap-3 min-w-0">
                            <span class="mt-1.5 h-2 w-2 flex-shrink-0 rounded-full" :style="'background-color: ' + faq.accentColor"></span>
                            <span class="text-sm font-medium text-gray-900 leading-snug" x-text="faq.question"></span>
                        </div>
                        <svg
                            class="mt-0.5 h-4 w-4 flex-shrink-0 text-gray-400 transition-transform duration-200"
                            :class="openId === faq.id ? 'rotate-180' : ''"
                            fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                    <div
                        x-show="openId === faq.id"
                        x-transition:enter="transition ease-out duration-150"
                        x-transition:enter-start="opacity-0 -translate-y-1"
                        x-transition:enter-end="opacity-100 translate-y-0"
                        x-transition:leave="transition ease-in duration-100"
                        x-transition:leave-start="opacity-100"
                        x-transition:leave-end="opacity-0"
                        class="px-5 pb-4 pl-10"
                    >
                        <p class="text-sm text-gray-600 leading-relaxed" x-text="faq.answer"></p>
                        <span
                            class="mt-3 inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                            :style="'background-color: ' + faq.accentColor + '18; color: ' + faq.accentColor"
                            x-text="faq.categoryLabel"
                        ></span>
                    </div>
                </div>
            </template>

            {{-- Empty state --}}
            <div
                x-show="filtered.length === 0"
                x-transition
                class="py-12 text-center rounded-xl border border-dashed border-gray-200"
            >
                <p class="text-sm font-medium text-gray-700">Nothing matched <span x-show="search !== ''" x-text="'&ldquo;' + search + '&rdquo;'"></span></p>
                <p class="mt-1 text-sm text-gray-400">Try a different keyword, or ask me directly below.</p>
            </div>

            {{-- Show more toggle --}}
            <div x-show="hasMore" class="pt-1 pb-2 text-center">
                <button
                    @click="showAll = true"
                    type="button"
                    class="text-xs text-gray-400 hover:text-gray-600 transition-colors"
                >
                    Show <span x-text="allMatching.length - 4"></span> more questions ↓
                </button>
            </div>

            {{-- Ask a question --}}
            <div class="mt-2 rounded-2xl bg-gradient-to-br from-slate-50 to-gray-100 border border-gray-200 p-5">
                @if(session('support_sent'))
                    <div class="flex items-start gap-3">
                        <div class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-full bg-green-100">
                            <svg class="h-4 w-4 text-green-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-gray-900">Got your message — thank you!</p>
                            <p class="mt-0.5 text-sm text-gray-500">I'll reply to <strong>{{ auth()->user()->email }}</strong> as soon as I can.</p>
                        </div>
                    </div>
                @else
                    <p class="text-sm font-semibold text-gray-900">Have a question or thought?</p>
                    <p class="mt-1 text-xs text-gray-500 leading-relaxed">
                        Something not working, something missing, or just not sure if Spacepad is the right fit? Tell me — I read every message personally and use your feedback to improve the product.
                    </p>
                    <form action="{{ route('support.ask') }}" method="POST" class="mt-3 flex flex-col gap-3">
                        @csrf
                        <textarea
                            name="message"
                            rows="4"
                            placeholder="What's on your mind? Questions, blockers, honest feedback — all welcome."
                            required
                            minlength="10"
                            maxlength="2000"
                            class="block w-full rounded-lg border border-gray-200 bg-white px-3.5 py-2.5 text-sm text-gray-900 placeholder-gray-400 shadow-sm focus:border-violet-500 focus:outline-none focus:ring-1 focus:ring-violet-500 resize-none"
                        >{{ old('message') }}</textarea>
                        @error('message')
                            <p class="text-xs text-red-600">{{ $message }}</p>
                        @enderror
                        <div class="flex items-center justify-between">
                            <p class="text-xs text-gray-400">Reply to <span class="font-medium">{{ auth()->user()->email }}</span></p>
                            <button
                                type="submit"
                                class="inline-flex items-center gap-2 rounded-lg bg-violet-600 px-4 py-2.5 text-sm font-medium text-white transition-colors hover:bg-violet-500"
                            >
                                Send
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/>
                                </svg>
                            </button>
                        </div>
                    </form>
                @endif
            </div>

        </div>

        {{-- Scrollable content: Roadmap tab --}}
        <div x-show="activeTab === 'roadmap'" class="flex-1 overflow-y-auto px-6 py-5 space-y-3">

            {{-- Roadmap items --}}
            <template x-if="roadmapItems.length > 0">
                <div class="space-y-3">
                    <template x-for="item in roadmapItems" :key="item.id">
                        <div class="rounded-xl border border-gray-200 bg-white p-4 transition-all hover:border-gray-300 hover:shadow-sm">
                            <div class="flex items-start gap-3">
                                {{-- Vote button --}}
                                <button
                                    @click="vote(item)"
                                    type="button"
                                    class="flex flex-shrink-0 flex-col items-center gap-0.5 rounded-lg border px-2.5 py-2 text-xs font-semibold transition-all"
                                    :class="item.voted
                                        ? 'border-violet-300 bg-violet-50 text-violet-700'
                                        : 'border-gray-200 bg-white text-gray-500 hover:border-violet-300 hover:text-violet-600'"
                                >
                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 10.5L12 3m0 0l7.5 7.5M12 3v18"/>
                                    </svg>
                                    <span x-text="item.votesCount"></span>
                                </button>

                                {{-- Content --}}
                                <div class="flex-1 min-w-0">
                                    <div class="flex flex-wrap items-center gap-2 mb-1">
                                        <p class="text-sm font-medium text-gray-900" x-text="item.title"></p>
                                    </div>
                                    <p x-show="item.description" class="text-xs text-gray-500 leading-relaxed mb-2" x-text="item.description"></p>
                                    <div class="flex flex-wrap items-center gap-2">
                                        {{-- Status badge --}}
                                        <span
                                            class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-medium"
                                            :class="item.statusClass"
                                        >
                                            <span x-show="item.isPulse" class="relative flex h-1.5 w-1.5">
                                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span>
                                                <span class="relative inline-flex h-1.5 w-1.5 rounded-full bg-amber-500"></span>
                                            </span>
                                            <span x-text="item.statusLabel"></span>
                                        </span>
                                        {{-- Expected date --}}
                                        <span x-show="item.expectedAt" class="inline-flex items-center gap-1 text-xs text-gray-400">
                                            <svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5"/>
                                            </svg>
                                            <span x-text="item.expectedAt"></span>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </template>

            {{-- Empty roadmap state --}}
            <template x-if="roadmapItems.length === 0">
                <div class="py-10 text-center">
                    <p class="text-sm text-gray-500">No roadmap items yet — check back soon.</p>
                </div>
            </template>

            {{-- Submit an idea --}}
            <div class="mt-2 rounded-2xl bg-gradient-to-br from-slate-50 to-gray-100 border border-gray-200 p-5">
                @if(session('suggestion_sent'))
                    <div class="flex items-start gap-3">
                        <div class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-full bg-green-100">
                            <svg class="h-4 w-4 text-green-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-gray-900">Got your idea — thanks!</p>
                            <p class="mt-0.5 text-sm text-gray-500">I'll review it and add it to the roadmap if it fits. I'll let you know.</p>
                        </div>
                    </div>
                @else
                    <p class="text-sm font-semibold text-gray-900">Missing something?</p>
                    <p class="mt-1 text-xs text-gray-500 leading-relaxed">
                        Submit a feature request or idea. I review every submission and add good ones to the roadmap above.
                    </p>
                    <form action="{{ route('roadmap.suggest') }}" method="POST" class="mt-3 flex flex-col gap-2.5">
                        @csrf
                        <input
                            type="text"
                            name="suggestion_title"
                            value="{{ old('suggestion_title') }}"
                            required
                            minlength="5"
                            maxlength="150"
                            placeholder="Feature title (e.g. Dark mode for displays)"
                            class="block w-full rounded-lg border border-gray-200 bg-white px-3.5 py-2 text-sm text-gray-900 placeholder-gray-400 shadow-sm focus:border-violet-500 focus:outline-none focus:ring-1 focus:ring-violet-500"
                        />
                        @error('suggestion_title') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                        <textarea
                            name="suggestion_description"
                            rows="2"
                            maxlength="1000"
                            placeholder="Optional — more context or why it matters to you"
                            class="block w-full rounded-lg border border-gray-200 bg-white px-3.5 py-2.5 text-sm text-gray-900 placeholder-gray-400 shadow-sm focus:border-violet-500 focus:outline-none focus:ring-1 focus:ring-violet-500 resize-none"
                        >{{ old('suggestion_description') }}</textarea>
                        @error('suggestion_description') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                        <div class="flex justify-end">
                            <button
                                type="submit"
                                class="inline-flex items-center gap-2 rounded-lg bg-violet-600 px-4 py-2.5 text-sm font-medium text-white transition-colors hover:bg-violet-500"
                            >
                                Submit idea
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/>
                                </svg>
                            </button>
                        </div>
                    </form>
                @endif
            </div>

        </div>
    </div>


</div>
