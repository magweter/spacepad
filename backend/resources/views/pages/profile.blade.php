@extends('layouts.base')
@section('title', 'Account')
@section('content')
    <x-alerts.alert />

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <x-cards.card>
            <div class="px-6 py-5">
                <h2 class="text-base font-semibold text-gray-900 mb-1">Account details</h2>
                <p class="text-sm text-gray-500 mb-4">Your personal account information.</p>

                <dl class="divide-y divide-gray-100">
                    <div class="py-3 sm:grid sm:grid-cols-3 sm:gap-4">
                        <dt class="text-sm font-medium text-gray-500">Name</dt>
                        <dd class="mt-1 text-sm text-gray-900 sm:col-span-2 sm:mt-0">{{ auth()->user()->name }}</dd>
                    </div>
                    <div class="py-3 sm:grid sm:grid-cols-3 sm:gap-4">
                        <dt class="text-sm font-medium text-gray-500">Email</dt>
                        <dd class="mt-1 text-sm text-gray-900 sm:col-span-2 sm:mt-0">{{ auth()->user()->email }}</dd>
                    </div>
                    <div class="py-3 sm:grid sm:grid-cols-3 sm:gap-4">
                        <dt class="text-sm font-medium text-gray-500">Member since</dt>
                        <dd class="mt-1 text-sm text-gray-900 sm:col-span-2 sm:mt-0">{{ auth()->user()->created_at->format('d M Y') }}</dd>
                    </div>
                </dl>
            </div>
        </x-cards.card>

        @if(auth()->user()->hasProForCurrentWorkspace() && $usageBreakdown)
        <x-cards.card>
            <div class="px-6 py-5">
                <h2 class="text-base font-semibold text-gray-900 mb-1">Billing</h2>
                <p class="text-sm text-gray-500 mb-4">Your current subscription usage for this workspace.</p>

                <div class="bg-gray-50 rounded-lg p-4 mb-4">
                    <dl class="grid grid-cols-3 gap-4">
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Displays</dt>
                            <dd class="mt-1 text-2xl font-semibold text-gray-900">{{ $usageBreakdown['displays'] }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Boards</dt>
                            <dd class="mt-1 text-2xl font-semibold text-gray-900">{{ $usageBreakdown['boards'] }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Total units</dt>
                            <dd class="mt-1 text-2xl font-semibold text-blue-700">{{ $usageBreakdown['total'] }}</dd>
                        </div>
                    </dl>
                </div>

                <div class="space-y-2 mb-5">
                    <div class="flex items-center justify-between text-sm py-2 border-b border-gray-100">
                        <span class="text-gray-700">{{ $usageBreakdown['displays'] }} display(s) × 1</span>
                        <span class="font-medium text-gray-900">{{ $usageBreakdown['displays'] }} unit(s)</span>
                    </div>
                    <div class="flex items-center justify-between text-sm py-2 border-b border-gray-100">
                        <span class="text-gray-700">{{ $usageBreakdown['boards'] }} board(s) × 2</span>
                        <span class="font-medium text-gray-900">{{ $usageBreakdown['board_usage'] }} unit(s)</span>
                    </div>
                    <div class="flex items-center justify-between text-sm py-2">
                        <span class="font-medium text-blue-900">Total billed to subscription</span>
                        <span class="font-bold text-blue-900">{{ $usageBreakdown['total'] }} unit(s)</span>
                    </div>
                </div>

                <button type="button"
                    onclick="window.dispatchEvent(new CustomEvent('open-modal', { detail: 'manage-subscription' }))"
                    class="rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                    Manage subscription
                </button>
            </div>
        </x-cards.card>
        @endif
    </div>

    <x-cards.card class="border border-red-200 bg-red-50">
        <div class="px-6 py-5">
            <h2 class="text-base font-semibold text-red-900 mb-1">Delete account</h2>
            <p class="text-sm text-red-700 mb-3">
                Permanently delete your account and all associated data. This action cannot be undone.
            </p>
            <ul class="text-sm text-red-700 list-disc list-inside space-y-1 mb-5">
                <li>All connected calendar accounts (Outlook, Google, CalDAV)</li>
                <li>All displays and their settings</li>
                <li>All devices, rooms and calendars</li>
                <li>All workspace memberships (owned workspaces without other members will be deleted)</li>
            </ul>

            <form action="{{ route('profile.destroy') }}" method="POST"
                x-data="{ open: false }"
                @submit.prevent="if(open) $el.submit(); else open = true">
                @csrf
                @method('DELETE')

                <div x-show="open" x-cloak class="mb-4">
                    <label for="confirm_email" class="block text-sm font-medium text-red-800 mb-1">
                        Type your email address to confirm:
                    </label>
                    <input
                        type="email"
                        id="confirm_email"
                        name="confirm_email"
                        autocomplete="off"
                        class="mt-1 px-3 py-2 block w-full border rounded-md border-red-300 focus:border-red-500 focus:ring-red-500 sm:text-sm bg-white"
                        placeholder="{{ auth()->user()->email }}"
                    >
                    @error('confirm_email')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit"
                    :class="open ? 'bg-red-600 hover:bg-red-700' : 'bg-white border border-red-400 text-red-700 hover:bg-red-100'"
                    class="rounded-md px-3 py-2 text-sm font-semibold shadow-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-600"
                    :class="open ? 'text-white' : 'text-red-700'">
                    <span x-text="open ? 'Permanently delete my account' : 'Delete my account'">Delete my account</span>
                </button>
            </form>
        </div>
    </x-cards.card>
@endsection
