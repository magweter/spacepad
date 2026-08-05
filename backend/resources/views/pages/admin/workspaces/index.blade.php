@extends('layouts.base')

@section('title', 'Workspaces')

@section('content')
    <x-admin.stats />

    <x-admin.tabs active="workspaces" />

    <div class="bg-white shadow rounded-lg p-6">
        <form method="GET" action="{{ route('admin.workspaces.index') }}" class="mb-4">
            <input
                type="text"
                name="search"
                value="{{ request('search') }}"
                placeholder="Search by workspace name, or by a member's name or email..."
                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm px-3 py-2 border"
            >
        </form>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-300">
                <thead>
                <tr>
                    <th class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900 sm:pl-0">Workspace</th>
                    <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Billing owner</th>
                    <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Members</th>
                    <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Displays</th>
                    <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Boards</th>
                    <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Units</th>
                    <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Plan</th>
                    <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">MRR</th>
                    <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Created</th>
                    <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Actions</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                @forelse($workspaces as $workspace)
                    @php
                        $owner = $workspace->billingOwner();
                        $mrr = $mrrByWorkspace[$workspace->id] ?? null;
                        $status = $workspace->billingStatus();
                    @endphp
                    <tr>
                        <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-0">{{ $workspace->name }}</td>
                        <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">{{ $owner?->email ?? '-' }}</td>
                        <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">{{ $workspace->members_count }}</td>
                        <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">{{ $workspace->displays_count }}</td>
                        <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">{{ $workspace->boards_count }}</td>
                        <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">{{ $workspace->getTotalUsageCount() }}</td>
                        <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                            @if($status === 'none')
                                <span class="inline-flex items-center rounded-md bg-gray-50 px-2 py-1 text-xs font-medium text-gray-700 ring-1 ring-inset ring-gray-600/20">Free</span>
                            @elseif($status === 'manual')
                                <span class="inline-flex items-center rounded-md bg-amber-50 px-2 py-1 text-xs font-medium text-amber-700 ring-1 ring-inset ring-amber-600/20">Manually billed</span>
                            @elseif($status === 'unlimited')
                                <span class="inline-flex items-center rounded-md bg-purple-50 px-2 py-1 text-xs font-medium text-purple-700 ring-1 ring-inset ring-purple-600/20">Unlimited</span>
                            @elseif(in_array($status, ['active', 'on_trial'], true))
                                <span class="inline-flex items-center rounded-md bg-green-50 px-2 py-1 text-xs font-medium text-green-700 ring-1 ring-inset ring-green-600/20">{{ Str::headline($status) }}</span>
                            @else
                                <span class="inline-flex items-center rounded-md bg-red-50 px-2 py-1 text-xs font-medium text-red-700 ring-1 ring-inset ring-red-600/20">{{ Str::headline($status) }}</span>
                            @endif
                        </td>
                        <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">{{ $mrr === null ? '-' : '$'.number_format($mrr, 2) }}</td>
                        <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">{{ $workspace->created_at?->format('Y-m-d') ?? '-' }}</td>
                        <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                            <div class="flex items-center gap-2">
                                <a href="{{ route('admin.workspaces.show', $workspace) }}" class="text-blue-600 hover:text-blue-900 font-medium">View</a>
                                @if($owner)
                                    <a href="{{ route('admin.users.show', $owner) }}" class="text-gray-600 hover:text-gray-900 font-medium">Owner</a>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="py-8 text-center text-sm text-gray-500">
                            @if(request('search'))
                                No workspaces found matching "{{ request('search') }}"
                            @else
                                No workspaces found
                            @endif
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if($workspaces->hasPages())
            <div class="mt-6">
                {{ $workspaces->links('vendor.pagination.tailwind') }}
            </div>
        @endif
    </div>
@endsection
