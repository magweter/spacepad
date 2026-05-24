@extends('layouts.base')
@section('title', 'Roadmap')

@section('actions')
    <a href="{{ route('admin.roadmap.create') }}" class="ml-auto inline-flex items-center rounded-md bg-oxford px-3 py-2 text-sm font-semibold text-white hover:bg-oxford-600">
        + New item
    </a>
@endsection

@section('content')
    <x-alerts.alert :errors="$errors" />

    {{-- Pending user suggestions --}}
    @php $pending = $items->where('is_approved', false); @endphp
    @if($pending->count())
        <div class="mb-8">
            <h2 class="mb-3 text-base font-semibold text-gray-900">Pending user suggestions ({{ $pending->count() }})</h2>
            <div class="overflow-hidden rounded-lg border border-amber-200 bg-white shadow-sm">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-amber-50">
                        <tr>
                            <th class="py-3 pl-4 pr-3 text-left text-xs font-medium text-gray-500">Title</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500">Submitted by</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500">Date</th>
                            <th class="px-3 py-3 text-right text-xs font-medium text-gray-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($pending as $item)
                            <tr>
                                <td class="py-3 pl-4 pr-3">
                                    <p class="text-sm font-medium text-gray-900">{{ $item->title }}</p>
                                    @if($item->description)
                                        <p class="mt-0.5 text-xs text-gray-500">{{ Str::limit($item->description, 100) }}</p>
                                    @endif
                                </td>
                                <td class="px-3 py-3 text-sm text-gray-500">
                                    {{ $item->submittedBy?->name ?? '—' }}<br>
                                    <span class="text-xs text-gray-400">{{ $item->submittedBy?->email }}</span>
                                </td>
                                <td class="px-3 py-3 text-sm text-gray-500">{{ $item->created_at->format('d M Y') }}</td>
                                <td class="px-3 py-3 text-right">
                                    <div class="flex justify-end gap-2">
                                        <form action="{{ route('admin.roadmap.approve', $item) }}" method="POST">
                                            @csrf
                                            <button type="submit" class="rounded bg-green-600 px-2.5 py-1 text-xs font-medium text-white hover:bg-green-500">Approve</button>
                                        </form>
                                        <a href="{{ route('admin.roadmap.edit', $item) }}" class="rounded bg-white px-2.5 py-1 text-xs font-medium text-gray-700 ring-1 ring-inset ring-gray-300 hover:bg-gray-50">Edit</a>
                                        <form action="{{ route('admin.roadmap.destroy', $item) }}" method="POST" onsubmit="return confirm('Delete this suggestion?')">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="rounded bg-white px-2.5 py-1 text-xs font-medium text-red-600 ring-1 ring-inset ring-red-300 hover:bg-red-50">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- Approved items --}}
    @php $approved = $items->where('is_approved', true); @endphp
    <div>
        <h2 class="mb-3 text-base font-semibold text-gray-900">Public roadmap ({{ $approved->count() }} items)</h2>
        <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="py-3 pl-4 pr-3 text-left text-xs font-medium text-gray-500">Title</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500">Status</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500">Expected</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500">Votes</th>
                        <th class="px-3 py-3 text-right text-xs font-medium text-gray-500">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($approved as $item)
                        <tr>
                            <td class="py-3 pl-4 pr-3">
                                <p class="text-sm font-medium text-gray-900">{{ $item->title }}</p>
                                @if($item->description)
                                    <p class="mt-0.5 text-xs text-gray-500">{{ Str::limit($item->description, 80) }}</p>
                                @endif
                            </td>
                            <td class="px-3 py-3">
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $item->status->badgeClass() }}">
                                    {{ $item->status->label() }}
                                </span>
                            </td>
                            <td class="px-3 py-3 text-sm text-gray-500">
                                {{ $item->expected_at?->format('M Y') ?? '—' }}
                            </td>
                            <td class="px-3 py-3 text-sm font-semibold text-gray-700">
                                {{ $item->votes_count }}
                            </td>
                            <td class="px-3 py-3 text-right">
                                <div class="flex justify-end gap-2">
                                    <a href="{{ route('admin.roadmap.edit', $item) }}" class="rounded bg-white px-2.5 py-1 text-xs font-medium text-gray-700 ring-1 ring-inset ring-gray-300 hover:bg-gray-50">Edit</a>
                                    <form action="{{ route('admin.roadmap.destroy', $item) }}" method="POST" onsubmit="return confirm('Delete this item?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="rounded bg-white px-2.5 py-1 text-xs font-medium text-red-600 ring-1 ring-inset ring-red-300 hover:bg-red-50">Delete</button>
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
@endsection
