@extends('layouts.base')

@section('title', 'Admin dashboard')

@section('content')
    <div x-data="{ activeTab: 'users-overview' }">
        <!-- Tab Navigation -->
        <div class="border-b border-gray-200">
            <nav class="-mb-px flex space-x-8" aria-label="Tabs">
                <button
                    @click="activeTab = 'users-overview'"
                    :class="activeTab === 'users-overview' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700'"
                    class="whitespace-nowrap border-b-2 py-4 px-1 text-sm font-medium"
                >
                    Users Overview
                </button>
                <button
                    @click="activeTab = 'instances'"
                    :class="activeTab === 'instances' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700'"
                    class="whitespace-nowrap border-b-2 py-4 px-1 text-sm font-medium"
                >
                    Self-Hosted Instances
                </button>
                <button
                    @click="activeTab = 'active-users'"
                    :class="activeTab === 'active-users' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700'"
                    class="whitespace-nowrap border-b-2 py-4 px-1 text-sm font-medium"
                >
                    Cloud-Hosted Users
                </button>
                <button
                    @click="activeTab = 'paying-users'"
                    :class="activeTab === 'paying-users' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700'"
                    class="whitespace-nowrap border-b-2 py-4 px-1 text-sm font-medium"
                >
                    Paying Cloud-Hosted Users
                </button>
            </nav>
        </div>

        <!-- Tab 1: Self-Hosted Instances -->
        <div x-show="activeTab === 'instances'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
            <div class="mt-6 grid grid-cols-1 gap-6 sm:grid-cols-2">
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
                                <tr><td colspan="6" class="text-center py-4 text-gray-400">No active self-hosted instances found.</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab 2: Active Cloud-Hosted Users -->
        <div x-show="activeTab === 'active-users'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
            <div class="mt-6 grid grid-cols-1 gap-6 sm:grid-cols-2">
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
                                <tr><td colspan="6" class="text-center py-4 text-gray-400">No active cloud-hosted users found.</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab 3: Paying Cloud-Hosted Users -->
        <div x-show="activeTab === 'paying-users'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
            <div class="mt-6 grid grid-cols-1 gap-6 sm:grid-cols-3">
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5 flex items-center">
                        <div class="flex-shrink-0">
                            <!-- Heroicon: Users (outline) -->
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-8 w-8 text-purple-500"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" /></svg>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Paying Users</dt>
                                <dd class="mt-1 text-2xl font-semibold text-gray-900">{{ $payingUsersCount }}</dd>
                            </dl>
                        </div>
                    </div>
                </div>
                <div class="bg-green-50 border border-green-200 rounded-lg overflow-hidden shadow">
                    <div class="p-5 flex items-center">
                        <div class="flex-shrink-0">
                            <!-- Heroicon: CurrencyDollar (outline) -->
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-8 w-8 text-green-600"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-green-600 truncate">Total MRR</dt>
                                <dd class="mt-1 text-2xl font-bold text-green-700">${{ number_format($totalMRR, 2) }}</dd>
                            </dl>
                        </div>
                    </div>
                </div>
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg overflow-hidden shadow">
                    <div class="p-5 flex items-center">
                        <div class="flex-shrink-0">
                            <!-- Heroicon: ChartBar (outline) -->
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-8 w-8 text-yellow-600"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" /></svg>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-yellow-600 truncate">Forecasted MRR</dt>
                                <dd class="mt-1 text-2xl font-bold text-yellow-700">${{ number_format($forecastedMRR, 2) }}</dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-10">
                <h2 class="text-xl font-bold mb-4">Paying Cloud-Hosted Users ({{ $payingUsersCount }})</h2>
                <div class="bg-white shadow rounded-lg p-6">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-300">
                            <thead>
                            <tr>
                                <th class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900 sm:pl-0">Name</th>
                                <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Email</th>
                                <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Usage Type</th>
                                <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Displays</th>
                                <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Subscription Status</th>
                                <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">LS Status</th>
                                <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Price</th>
                                <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">MRR</th>
                                <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Subscription Ends</th>
                                <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Registered</th>
                            </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                            @forelse($payingUsers as $user)
                                <tr>
                                    <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-0">{{ $user->name }}</td>
                                    <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">{{ $user->email }}</td>
                                    <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">{{ $user->usage_type?->label() }}</td>
                                    <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">{{ $user->displays_count }}</td>
                                    <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                                        <span class="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset {{ $user->is_unlimited ? 'bg-green-50 text-green-700 ring-green-600/20' : 'bg-blue-50 text-blue-700 ring-blue-600/20' }}">
                                            {{ $user->subscription_status }}
                                        </span>
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                                        @if($user->lemon_squeezy_status)
                                            @php
                                                $status = $user->lemon_squeezy_status;
                                                $statusLabel = ucwords(str_replace('_', ' ', $status));
                                                $statusColors = match($status) {
                                                    'active' => 'bg-green-50 text-green-700 ring-green-600/20',
                                                    'past_due' => 'bg-yellow-50 text-yellow-700 ring-yellow-600/20',
                                                    'unpaid' => 'bg-red-50 text-red-700 ring-red-600/20',
                                                    'cancelled' => 'bg-gray-50 text-gray-700 ring-gray-600/20',
                                                    'on_trial' => 'bg-blue-50 text-blue-700 ring-blue-600/20',
                                                    'paused' => 'bg-orange-50 text-orange-700 ring-orange-600/20',
                                                    default => 'bg-gray-50 text-gray-700 ring-gray-600/20',
                                                };
                                            @endphp
                                            <span class="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset {{ $statusColors }}">
                                                {{ $statusLabel }}
                                            </span>
                                        @else
                                            <span class="text-gray-400">-</span>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                                        @if($user->price > 0)
                                            <span class="font-semibold text-gray-900">${{ number_format($user->price, 2) }}</span>
                                        @else
                                            <span class="text-gray-400">-</span>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                                        @if($user->mrr > 0)
                                            <span class="font-semibold text-gray-900">${{ number_format($user->mrr, 2) }}</span>
                                        @else
                                            <span class="text-gray-400">-</span>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                                        @if($user->subscription_ends_at)
                                            {{ \Carbon\Carbon::parse($user->subscription_ends_at)->format('Y-m-d') }}
                                        @else
                                            <span class="text-gray-400">-</span>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">{{ $user->created_at->format('Y-m-d') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="10" class="text-center py-4 text-gray-400">No paying cloud-hosted users found.</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab 4: Users Overview -->
        <div x-show="activeTab === 'users-overview'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
            <div class="mt-6 grid grid-cols-1 gap-6 sm:grid-cols-3">
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5 flex items-center">
                        <div class="flex-shrink-0">
                            <!-- Heroicon: Users (outline) -->
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-8 w-8 text-purple-500"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" /></svg>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Total Users</dt>
                                <dd class="mt-1 text-2xl font-semibold text-gray-900">{{ $allUsers->count() }}</dd>
                            </dl>
                        </div>
                    </div>
                </div>
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5 flex items-center">
                        <div class="flex-shrink-0">
                            <!-- Heroicon: UserCircle (outline) -->
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-8 w-8 text-blue-500"><path stroke-linecap="round" stroke-linejoin="round" d="M17.982 18.725A7.488 7.488 0 0012 15.75a7.488 7.488 0 00-5.982 2.975m11.963 0a9 9 0 10-11.963 0m11.963 0A8.966 8.966 0 0112 21a8.966 8.966 0 01-5.982-2.275M15 9.75a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Users with Displays</dt>
                                <dd class="mt-1 text-2xl font-semibold text-gray-900">{{ $allUsers->filter(fn($u) => $u->displays_count > 0)->count() }}</dd>
                            </dl>
                        </div>
                    </div>
                </div>
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5 flex items-center">
                        <div class="flex-shrink-0">
                            <!-- Heroicon: CheckCircle (outline) -->
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-8 w-8 text-green-500"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Pro Users</dt>
                                <dd class="mt-1 text-2xl font-semibold text-gray-900">{{ $allUsers->filter(fn($u) => $u->hasPro())->count() }}</dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-10">
                <h2 class="text-xl font-bold mb-4">All Users</h2>
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
                                            <a href="{{ route('admin.users.show', $user) }}" class="text-blue-600 hover:text-blue-900 font-medium">
                                                View
                                            </a>
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
                                    <td colspan="8" class="py-8 text-center text-sm text-gray-500">
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
        </div>
    </div>
@endsection
