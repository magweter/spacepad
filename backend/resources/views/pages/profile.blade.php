@extends('layouts.base')
@section('title', 'Account')
@section('content')
    <x-alerts.alert />

    {{-- Personal only. Subscription and usage live on Manage workspace, because they belong to
         a workspace rather than to you — this page must stay the same whichever workspace you
         happen to have selected. --}}
    <x-cards.card class="mb-6">
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
    </x-cards.card>

    <x-cards.card class="border border-red-200 bg-red-50">
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
    </x-cards.card>
@endsection
