@extends('layouts.blank')
@section('title', 'Wrong account')

@section('page')
    <x-invitations.shell heading="You're signed in as someone else">
        <p class="text-sm text-gray-600 mb-6">
            This invitation was sent to
            <strong class="font-medium text-gray-900">{{ $invitation->email }}</strong>,
            but you are signed in as
            <strong class="font-medium text-gray-900">{{ auth()->user()->email }}</strong>.
        </p>

        <p class="text-sm text-gray-600 mb-6">
            Sign out to accept it as {{ $invitation->email }}. Your current account is not
            affected, and nothing is added to it.
        </p>

        <form action="{{ route('invitations.switch-account', $token) }}" method="POST">
            @csrf
            <button type="submit"
                class="w-full rounded-md bg-oxford px-3 py-2 text-sm font-semibold text-white">
                Sign out and continue
            </button>
        </form>

        <a href="{{ route('dashboard') }}"
            class="mt-3 block w-full rounded-md bg-white px-3 py-2 text-center text-sm font-semibold text-gray-700 ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
            Cancel
        </a>
    </x-invitations.shell>
@endsection
