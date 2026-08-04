@extends('layouts.blank')
@section('title', 'Sign in not allowed')

@section('page')
    <x-invitations.shell heading="This address cannot sign in">
        <p class="text-sm text-gray-600 mb-6">
            <strong class="font-medium text-gray-900">{{ $invitation->email }}</strong>
            is not on the list of addresses allowed to sign in to this Spacepad instance.
            Ask your administrator to allow it, then open the invitation again.
        </p>

        <a href="{{ route('login') }}"
            class="block w-full rounded-md bg-white px-3 py-2 text-center text-sm font-semibold text-gray-700 ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
            Go to sign in
        </a>
    </x-invitations.shell>
@endsection
