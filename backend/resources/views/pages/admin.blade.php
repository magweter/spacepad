@extends('layouts.base')

@section('title', 'Admin dashboard')

@section('content')
    <!-- Stats -->
    <div class="grid grid-cols-2 gap-4 sm:grid-cols-4 mb-6">
        <div class="bg-white border border-gray-200 rounded-lg p-4">
            <dt class="text-xs font-medium text-gray-500 truncate">Total Users</dt>
            <dd class="mt-1 text-xl font-semibold text-gray-900">{{ $allUsers->total() }}</dd>
        </div>
        <div class="bg-white border border-gray-200 rounded-lg p-4">
            <dt class="text-xs font-medium text-gray-500 truncate">Active Users</dt>
            <dd class="mt-1 text-xl font-semibold text-gray-900">{{ $activeUsersCount }}</dd>
        </div>
        <div class="bg-white border border-gray-200 rounded-lg p-4">
            <dt class="text-xs font-medium text-gray-500 truncate">Active Instances</dt>
            <dd class="mt-1 text-xl font-semibold text-gray-900">{{ $activeInstancesCount }}</dd>
        </div>
        <div class="bg-white border border-gray-200 rounded-lg p-4">
            <dt class="text-xs font-medium text-gray-500 truncate">Total Instances</dt>
            <dd class="mt-1 text-xl font-semibold text-gray-900">{{ $totalInstances }}</dd>
        </div>
    </div>

    <div x-data="{ tab: new URLSearchParams(window.location.search).get('tab') || 'users' }">
        <!-- Tab Navigation -->
        <div class="border-b border-gray-200 mb-6">
            <nav class="-mb-px flex space-x-8">
                <button
                    @click="tab = 'users'"
                    :class="tab === 'users' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700'"
                    class="whitespace-nowrap border-b-2 py-4 px-1 text-sm font-medium"
                >
                    Users
                </button>
                <button
                    @click="tab = 'roadmap'"
                    :class="tab === 'roadmap' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700'"
                    class="whitespace-nowrap border-b-2 py-4 px-1 text-sm font-medium"
                >
                    Roadmap
                    @php $pendingCount = $roadmapItems->where('is_approved', false)->count(); @endphp
                    @if($pendingCount)
                        <span class="ml-1.5 inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-700">{{ $pendingCount }}</span>
                    @endif
                </button>
            </nav>
        </div>

        <!-- Users Tab -->
        <div x-show="tab === 'users'">
            <div class="bg-white shadow rounded-lg p-6">
                <form method="GET" action="{{ route('admin.index') }}" class="mb-4">
                    <input
                        type="text"
                        name="search"
                        value="{{ request('search') }}"
                        placeholder="Search by name or email..."
                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm px-3 py-2 border"
                    >
                </form>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-300">
                        <thead>
                        <tr>
                            <th class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900 sm:pl-0">Name</th>
                            <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Email</th>
                            <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Usage Type</th>
                            <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Displays</th>
                            <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Boards</th>
                            <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Pro</th>
                            <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Registered</th>
                            <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Last Activity</th>
                            <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Actions</th>
                        </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                        @forelse($allUsers as $user)
                            <tr>
                                <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-0">{{ $user->name }}</td>
                                <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">{{ $user->email }}</td>
                                <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">{{ $user->usage_type?->label() ?? '-' }}</td>
                                <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">{{ $user->displays_count }}</td>
                                <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">{{ $user->boards_count ?? 0 }}</td>
                                <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                                    @if($user->hasPro())
                                        <span class="inline-flex items-center rounded-md bg-green-50 px-2 py-1 text-xs font-medium text-green-700 ring-1 ring-inset ring-green-600/20">Yes</span>
                                    @else
                                        <span class="inline-flex items-center rounded-md bg-gray-50 px-2 py-1 text-xs font-medium text-gray-700 ring-1 ring-inset ring-gray-600/20">No</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">{{ $user->created_at->format('Y-m-d') }}</td>
                                <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">{{ $user->last_activity_at ? $user->last_activity_at->format('Y-m-d') : 'Never' }}</td>
                                <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                                    <div class="flex items-center gap-2">
                                        <a href="{{ route('admin.users.show', $user) }}" class="text-blue-600 hover:text-blue-900 font-medium">View</a>
                                        <form action="{{ route('admin.users.impersonate', $user) }}" method="POST" class="inline">
                                            @csrf
                                            <button type="submit" class="text-purple-600 hover:text-purple-900 font-medium" onclick="return confirm('Are you sure you want to impersonate {{ $user->email }}?')">
                                                Impersonate
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="py-8 text-center text-sm text-gray-500">
                                    @if(request('search'))
                                        No users found matching "{{ request('search') }}"
                                    @else
                                        No users found
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                @if($allUsers->hasPages())
                    <div class="mt-6">
                        {{ $allUsers->links('vendor.pagination.tailwind') }}
                    </div>
                @endif
            </div>
        </div>

        <!-- Roadmap Tab -->
        <div x-show="tab === 'roadmap'">
            <div class="mb-4 flex justify-end">
                <a href="{{ route('admin.roadmap.create') }}" class="inline-flex items-center rounded-md bg-oxford px-3 py-2 text-sm font-semibold text-white hover:bg-oxford-600">
                    + New item
                </a>
            </div>

            <x-alerts.alert :errors="$errors" />

            @php $pending = $roadmapItems->where('is_approved', false); @endphp
            @if($pending->count())
                <div class="mb-8">
                    <h2 class="text-xl font-bold mb-4">Pending suggestions ({{ $pending->count() }})</h2>
                    <div class="bg-white shadow rounded-lg p-6">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-300">
                                <thead>
                                    <tr>
                                        <th class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900 sm:pl-0">Title</th>
                                        <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Submitted by</th>
                                        <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Date</th>
                                        <th class="px-3 py-3.5 text-right text-sm font-semibold text-gray-900">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    @foreach($pending as $item)
                                        <tr>
                                            <td class="py-4 pl-4 pr-3 sm:pl-0">
                                                <p class="text-sm font-medium text-gray-900">{{ $item->title }}</p>
                                                @if($item->description)
                                                    <p class="mt-0.5 text-xs text-gray-500">{{ Str::limit($item->description, 100) }}</p>
                                                @endif
                                            </td>
                                            <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                                                {{ $item->submittedBy?->name ?? '—' }}<br>
                                                <span class="text-xs text-gray-400">{{ $item->submittedBy?->email }}</span>
                                            </td>
                                            <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">{{ $item->created_at->format('d M Y') }}</td>
                                            <td class="whitespace-nowrap px-3 py-4 text-right">
                                                <div class="flex justify-end gap-2">
                                                    <form action="{{ route('admin.roadmap.approve', $item) }}" method="POST">
                                                        @csrf
                                                        <button type="submit" class="text-green-600 hover:text-green-900 font-medium text-sm">Approve</button>
                                                    </form>
                                                    <a href="{{ route('admin.roadmap.edit', $item) }}" class="text-blue-600 hover:text-blue-900 font-medium text-sm">Edit</a>
                                                    <form action="{{ route('admin.roadmap.destroy', $item) }}" method="POST" onsubmit="return confirm('Delete this suggestion?')">
                                                        @csrf @method('DELETE')
                                                        <button type="submit" class="text-red-600 hover:text-red-900 font-medium text-sm">Delete</button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endif

            @php $approved = $roadmapItems->where('is_approved', true); @endphp
            <div>
                <h2 class="text-xl font-bold mb-4">Public roadmap ({{ $approved->count() }} items)</h2>
                <div class="bg-white shadow rounded-lg p-6">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-300">
                            <thead>
                                <tr>
                                    <th class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900 sm:pl-0">Title</th>
                                    <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Status</th>
                                    <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Expected</th>
                                    <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Votes</th>
                                    <th class="px-3 py-3.5 text-right text-sm font-semibold text-gray-900">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                            @forelse($approved as $item)
                                <tr>
                                    <td class="py-4 pl-4 pr-3 sm:pl-0">
                                        <p class="text-sm font-medium text-gray-900">{{ $item->title }}</p>
                                        @if($item->description)
                                            <p class="mt-0.5 text-xs text-gray-500">{{ Str::limit($item->description, 80) }}</p>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-4">
                                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $item->status->badgeClass() }}">
                                            {{ $item->status->label() }}
                                        </span>
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">{{ $item->expected_at?->format('M Y') ?? '—' }}</td>
                                    <td class="whitespace-nowrap px-3 py-4 text-sm font-semibold text-gray-700">{{ $item->votes_count }}</td>
                                    <td class="whitespace-nowrap px-3 py-4 text-right">
                                        <div class="flex justify-end gap-2">
                                            <a href="{{ route('admin.roadmap.edit', $item) }}" class="text-blue-600 hover:text-blue-900 font-medium text-sm">Edit</a>
                                            <form action="{{ route('admin.roadmap.destroy', $item) }}" method="POST" onsubmit="return confirm('Delete this item?')">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="text-red-600 hover:text-red-900 font-medium text-sm">Delete</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="py-10 text-center text-sm text-gray-400">No roadmap items yet. <a href="{{ route('admin.roadmap.create') }}" class="text-blue-600 hover:underline">Create one</a>.</td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
