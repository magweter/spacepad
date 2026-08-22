@extends('layouts.blank')
@section('title', 'Invitation not found')

@section('page')
    <x-invitations.shell heading="This invitation link is not valid">
        <p class="text-sm text-gray-600 mb-6">
            It may have been withdrawn, already used, or replaced by a newer invitation. Ask your
            colleague to send you a fresh one.
        </p>

        <a href="{{ route('login') }}"
            class="block w-full rounded-md bg-white px-3 py-2 text-center text-sm font-semibold text-gray-700 ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
            Go to sign in
        </a>
    </x-invitations.shell>
@endsection
