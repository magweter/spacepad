@extends('layouts.base')
@section('title', 'User Details - ' . $user->email)
@section('container_class', 'max-w-4xl')

@section('content')
    <x-cards.card>
        <div class="sm:flex sm:items-center mb-6">
            <div class="sm:flex-auto">
                <h1 class="text-lg font-semibold leading-6 text-gray-900">User Details</h1>
                <p class="mt-1 text-sm text-gray-500">View and manage user account information</p>
            </div>
            <div class="mt-4 sm:ml-16 sm:mt-0 sm:flex-none">
                <a href="{{ route('admin.index') }}" class="inline-flex items-center gap-x-1.5 rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                    <x-icons.arrow-left class="h-4 w-4" />
                    Back to Admin
                </a>
            </div>
        </div>

        @if(session('error'))
            <div class="mb-6 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded">
                {{ session('error') }}
            </div>
        @endif

        @if(session('success'))
            <div class="mb-6 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded">
                {{ session('success') }}
            </div>
        @endif

        <div class="space-y-6">
            <div class="border border-gray-200 rounded-lg p-6">
                <h3 class="text-base font-semibold text-gray-900 mb-4">Account Information</h3>
                <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Email</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $user->email }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Name</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $user->name }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">User ID</dt>
                        <dd class="mt-1 text-sm text-gray-900 font-mono">{{ $user->id }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Usage Type</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $user->usage_type?->label() ?? 'Not set' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Created</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $user->created_at->format('Y-m-d H:i:s') }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Last Activity</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $user->last_activity_at ? $user->last_activity_at->format('Y-m-d H:i:s') : 'Never' }}</dd>
                    </div>
                </dl>
            </div>

            @if($user->hasPro() || $subscriptionInfo)
                <div class="border border-gray-200 rounded-lg p-6">
                    <h3 class="text-base font-semibold text-gray-900 mb-4">Subscription Information</h3>
                    <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Plan</dt>
                            <dd class="mt-1">
                                @if($user->is_unlimited)
                                    <span class="inline-flex items-center rounded-md bg-green-50 px-2 py-1 text-xs font-medium text-green-700 ring-1 ring-inset ring-green-600/20">Unlimited</span>
                                @elseif($subscriptionInfo)
                                    <span class="inline-flex items-center rounded-md bg-blue-50 px-2 py-1 text-xs font-medium text-blue-700 ring-1 ring-inset ring-blue-600/20">Pro</span>
                                @else
                                    <span class="inline-flex items-center rounded-md bg-gray-50 px-2 py-1 text-xs font-medium text-gray-700 ring-1 ring-inset ring-gray-600/20">Free</span>
                                @endif
                            </dd>
                        </div>
                        @if($subscriptionInfo)
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Status</dt>
                                <dd class="mt-1">
                                    @php
                                        $status = $subscriptionInfo['status'];
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
                                </dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Monthly Price</dt>
                                <dd class="mt-1 text-sm text-gray-900">${{ number_format($subscriptionInfo['price'], 2) }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">MRR</dt>
                                <dd class="mt-1 text-sm text-gray-900">${{ number_format($subscriptionInfo['mrr'], 2) }}</dd>
                            </div>
                            @if($subscriptionInfo['ends_at'])
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Subscription Ends</dt>
                                    <dd class="mt-1 text-sm text-gray-900">{{ \Carbon\Carbon::parse($subscriptionInfo['ends_at'])->format('Y-m-d') }}</dd>
                                </div>
                            @endif
                        @endif
                    </dl>
                </div>
            @endif

            <div class="border border-gray-200 rounded-lg p-6">
                <h3 class="text-base font-semibold text-gray-900 mb-4">Data Summary</h3>
                <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Outlook Accounts</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $user->outlookAccounts->count() }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Google Accounts</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $user->googleAccounts->count() }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">CalDAV Accounts</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $user->caldavAccounts->count() }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Displays</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $user->displays->count() }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Devices</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $user->devices->count() }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Workspaces</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $user->workspaces->count() }}</dd>
                    </div>
                </dl>
            </div>

            @if($user->id !== auth()->id())
                <div class="border border-red-200 rounded-lg p-6 bg-red-50">
                    <h3 class="text-base font-semibold text-red-900 mb-4">⚠️ Delete User Account</h3>
                    <p class="text-sm text-red-800 mb-3">
                        This action cannot be undone. All data associated with this user will be permanently deleted:
                    </p>
                    <ul class="text-sm text-red-800 list-disc list-inside space-y-1 mb-4">
                        <li>All connected accounts (Outlook, Google, CalDAV)</li>
                        <li>All displays and their settings</li>
                        <li>All devices</li>
                        <li>All calendars and events</li>
                        <li>All rooms</li>
                        <li>All workspace memberships</li>
                        <li>All personal access tokens</li>
                    </ul>

                    <form action="{{ route('admin.users.delete', $user) }}" method="POST" class="mt-4">
                        @csrf
                        @method('DELETE')

                        <div class="mb-4">
                            <label for="confirm_email" class="block text-sm font-medium text-gray-700 mb-2">
                                To confirm deletion, please type the user's email address:
                            </label>
                            <input
                                type="email"
                                id="confirm_email"
                                name="confirm_email"
                                required
                                class="mt-1 px-3 py-2 block w-full border rounded-md border-gray-300 focus:border-red-500 focus:ring-red-500 sm:text-sm"
                                placeholder="{{ $user->email }}"
                            >
                            @error('confirm_email')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="flex justify-end gap-x-3">
                            <button
                                type="submit"
                                class="rounded-md bg-red-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-red-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-600"
                                onclick="return confirm('Are you absolutely sure you want to delete this user account? This action cannot be undone.')"
                            >
                                Permanently delete user
                            </button>
                        </div>
                    </form>
                </div>
            @else
                <div class="border border-yellow-200 rounded-lg p-6 bg-yellow-50">
                    <h3 class="text-base font-semibold text-yellow-900 mb-2">⚠️ Notice</h3>
                    <p class="text-sm text-yellow-800">
                        You cannot delete your own account. Please ask another admin to perform this action if needed.
                    </p>
                </div>
            @endif
        </div>
    </x-cards.card>
@endsection
