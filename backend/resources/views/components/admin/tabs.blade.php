@props(['active' => null, 'pendingRoadmapCount' => 0])

{{--
    The admin panel's tab bar, shared by every admin screen.

    Users and Roadmap are two panes of the dashboard, switched in place by Alpine, so on
    that page they have to stay buttons inside its x-data. Workspaces and Merge are pages
    of their own. Passing `active` is what says "we are on one of those pages", which is
    also what tells this component the Alpine state it would bind to does not exist here.
--}}
@php
    $base = 'whitespace-nowrap border-b-2 py-4 px-1 text-sm font-medium';
    $idle = 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700';
    $on = 'border-blue-500 text-blue-600';
@endphp

<div class="border-b border-gray-200 mb-6">
    <nav class="-mb-px flex space-x-8">
        @if($active === null)
            <button
                @click="tab = 'users'"
                :class="tab === 'users' ? '{{ $on }}' : '{{ $idle }}'"
                class="{{ $base }}"
            >
                Users
            </button>
            <button
                @click="tab = 'roadmap'"
                :class="tab === 'roadmap' ? '{{ $on }}' : '{{ $idle }}'"
                class="{{ $base }}"
            >
                Roadmap
                @if($pendingRoadmapCount)
                    <span class="ml-1.5 inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-700">{{ $pendingRoadmapCount }}</span>
                @endif
            </button>
        @else
            <a href="{{ route('admin.index') }}" class="{{ $base }} {{ $idle }}">Users</a>
            <a href="{{ route('admin.index', ['tab' => 'roadmap']) }}" class="{{ $base }} {{ $idle }}">Roadmap</a>
        @endif

        <a href="{{ route('admin.workspaces.index') }}"
           class="{{ $base }} {{ $active === 'workspaces' ? $on : $idle }}">
            Workspaces
        </a>
        <a href="{{ route('admin.merge.index') }}"
           class="{{ $base }} {{ $active === 'merge' ? $on : $idle }}">
            Merge workspaces
        </a>
    </nav>
</div>
