@extends('layouts.blank')
@section('title', 'Invitation expired')

@section('page')
    <x-invitations.shell heading="This invitation has expired">
        <p class="text-sm text-gray-600 mb-6">
            Invitations are valid for {{ \App\Models\WorkspaceInvitation::LIFETIME_DAYS }} days.
            Ask {{ $invitation->invitedBy?->name ?? 'the person who invited you' }} to send a new one
            for <strong class="font-medium text-gray-900">{{ $invitation->email }}</strong>.
        </p>

        <a href="{{ route('login') }}"
            class="block w-full rounded-md bg-white px-3 py-2 text-center text-sm font-semibold text-gray-700 ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
            Go to sign in
        </a>
    </x-invitations.shell>
@endsection
