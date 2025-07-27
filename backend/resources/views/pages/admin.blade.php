@extends('layouts.base')

@section('title', 'Admin dashboard')

@section('content')
    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
        <div class="bg-white overflow-hidden shadow rounded-lg">
            <div class="p-5 flex items-center">
                <div class="flex-shrink-0">
                    <!-- Heroicon: PresentationChartBar (outline) -->
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-8 w-8 text-blue-500"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3v12a2.25 2.25 0 002.25 2.25h13.5A2.25 2.25 0 0021 15V3" /><path stroke-linecap="round" stroke-linejoin="round" d="M9 17v4m6-4v4M9 13.5V10.5m6 3V7.5" /></svg>
                </div>
                <div class="ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-sm font-medium text-gray-500 truncate">Active Displays</dt>
                        <dd class="mt-1 text-2xl font-semibold text-gray-900">{{ $activeDisplaysCount }}</dd>
                    </dl>
                </div>
            </div>
        </div>
        <div class="bg-white overflow-hidden shadow rounded-lg">
            <div class="p-5 flex items-center">
                <div class="flex-shrink-0">
                    <!-- Heroicon: RectangleStack (outline) -->
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-8 w-8 text-indigo-500"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7.5V6.75A2.25 2.25 0 015.25 4.5h13.5A2.25 2.25 0 0121 6.75v.75m-18 0v10.5A2.25 2.25 0 005.25 20.25h13.5A2.25 2.25 0 0021 18.75V8.25m-18 0h18" /></svg>
                </div>
                <div class="ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-sm font-medium text-gray-500 truncate">Total Displays</dt>
                        <dd class="mt-1 text-2xl font-semibold text-gray-900">{{ $totalDisplays }}</dd>
                    </dl>
                </div>
            </div>
        </div>
        <div class="bg-white overflow-hidden shadow rounded-lg">
            <div class="p-5 flex items-center">
                <div class="flex-shrink-0">
                    <!-- Heroicon: Bolt (outline) -->
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-8 w-8 text-green-500"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L6 14.25h7.5L10.5 19.5 18 9.75h-7.5z" /></svg>
                </div>
                <div class="ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-sm font-medium text-gray-500 truncate">Active Instances</dt>
                        <dd class="mt-1 text-2xl font-semibold text-gray-900">{{ $activeInstancesCount }}</dd>
                    </dl>
                </div>
            </div>
        </div>
        <div class="bg-white overflow-hidden shadow rounded-lg">
            <div class="p-5 flex items-center">
                <div class="flex-shrink-0">
                    <!-- Heroicon: Hexagon (outline) -->
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-8 w-8 text-yellow-500"><path stroke-linecap="round" stroke-linejoin="round" d="M3.25 7.5v9a2.25 2.25 0 001.125 1.95l6.75 3.9a2.25 2.25 0 002.25 0l6.75-3.9A2.25 2.25 0 0020.75 16.5v-9a2.25 2.25 0 00-1.125-1.95l-6.75-3.9a2.25 2.25 0 00-2.25 0l-6.75 3.9A2.25 2.25 0 003.25 7.5z" /></svg>
                </div>
                <div class="ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-sm font-medium text-gray-500 truncate">Total Instances</dt>
                        <dd class="mt-1 text-2xl font-semibold text-gray-900">{{ $totalInstances }}</dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-10">
        <h2 class="text-xl font-bold mb-4">Active Self-Hosted Instances</h2>
        <div class="bg-white shadow rounded-lg p-6">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-300">
                    <thead>
                    <tr>
                        <th class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900 sm:pl-0">Instance Key</th>
                        <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 align-top">Users</th>
                        <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 align-top">Displays</th>
                        <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 align-top">Rooms</th>
                        <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 align-top">Last Heartbeat</th>
                        <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 align-top">Paid</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                    @forelse($activeInstances as $instance)
                        <tr>
                            <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-0 align-top">{{ $instance->instance_key }}</td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500 align-top">
                                @if(is_array($instance->users))
                                    @foreach($instance->users as $user)
                                        <div>
                                            {{ $user['email'] ?? '' }}
                                            @if(!empty($user['usage_type']))
                                                ({{ ucfirst($user['usage_type']) }})
                                            @endif
                                        </div>
                                    @endforeach
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500 align-top">{{ $instance->displays_count }}</td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500 align-top">{{ $instance->rooms_count }}</td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500 align-top">{{ $instance->last_heartbeat_at?->diffForHumans() ?? '-' }}</td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500 align-top">{{ $instance->is_paid ? 'Yes' : 'No' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center py-4 text-gray-400">No active self-hosted instances found.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="mt-10">
        <h2 class="text-xl font-bold mb-4">Active Cloud-Hosted Users</h2>
        <div class="bg-white shadow rounded-lg p-6">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-300">
                    <thead>
                    <tr>
                        <th class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900 sm:pl-0">Name</th>
                        <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Email</th>
                        <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Usage Type</th>
                        <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Displays</th>
                        <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Last Display Activity</th>
                        <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Paid</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                    @forelse($activeDisplays as $user)
                        <tr>
                            <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-0">{{ $user->name }}</td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">{{ $user->email }}</td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">{{ $user->usage_type?->label() }}</td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">{{ $user->displays_count }}</td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">{{ $user->last_display_activity ? \Carbon\Carbon::parse($user->last_display_activity)->diffForHumans() : '-' }}</td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">{{ $user->is_paid ? 'Yes' : 'No' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center py-4 text-gray-400">No active cloud-hosted users found.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
