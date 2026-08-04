@extends('layouts.blank')
@section('title', 'Join '.$invitation->workspace->name)

@section('page')
    <x-invitations.shell heading="Join {{ $invitation->workspace->name }}">
        <p class="text-sm text-gray-600 mb-6">
            <strong class="font-medium text-gray-900">{{ $invitation->invitedBy?->name ?? 'A colleague' }}</strong>
            invited you to join the workspace
            <strong class="font-medium text-gray-900">{{ $invitation->workspace->name }}</strong>
            as {{ $invitation->role->label() }}.
        </p>

        <p class="text-sm text-gray-600 mb-6">
            You will manage the same displays, boards and calendar accounts as your colleagues.
        </p>

        <dl class="bg-gray-50 rounded-lg p-4 mb-6 text-sm">
            <div class="flex justify-between py-1">
                <dt class="text-gray-500">Continue as</dt>
                <dd class="font-medium text-gray-900">{{ $invitation->email }}</dd>
            </div>
            <div class="flex justify-between py-1">
                <dt class="text-gray-500">Role</dt>
                <dd class="font-medium text-gray-900">{{ $invitation->role->label() }}</dd>
            </div>
        </dl>

        <form action="{{ route('invitations.accept', $token) }}" method="POST">
            @csrf

            @if($movableWorkspaces->isNotEmpty())
                <div class="rounded-lg border border-gray-200 p-4 mb-6" x-data="{ move: false }">
                    <label class="flex items-start gap-2 text-sm">
                        <input type="checkbox" name="move_data" value="1" x-model="move"
                            class="mt-0.5 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        <span class="text-gray-900 font-medium">
                            Move my displays, boards and calendar accounts into {{ $invitation->workspace->name }}
                        </span>
                    </label>

                    <div x-show="move" x-cloak class="mt-3 space-y-3">
                        @if($movableWorkspaces->count() > 1)
                            <div>
                                <label for="source_workspace_id" class="block text-xs font-medium text-gray-700 mb-1">
                                    Move from
                                </label>
                                <select id="source_workspace_id" name="source_workspace_id"
                                    class="block w-full px-2 py-1.5 text-sm border rounded-md border-gray-300 focus:border-blue-500 focus:ring-blue-500">
                                    @foreach($movableWorkspaces as $entry)
                                        <option value="{{ $entry['workspace']->id }}">{{ $entry['workspace']->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @else
                            <input type="hidden" name="source_workspace_id" value="{{ $movableWorkspaces->first()['workspace']->id }}">
                        @endif

                        <ul class="text-xs text-gray-500 space-y-0.5">
                            @foreach($movableWorkspaces->first()['counts'] as $label => $count)
                                <li>{{ $count }} &times; {{ Str::headline($label) }}</li>
                            @endforeach
                        </ul>

                        <p class="text-xs text-gray-500">
                            Your old workspace stays behind, empty. You can delete it afterwards from
                            the Team page.
                        </p>
                    </div>
                </div>
            @endif

            <button type="submit"
                class="w-full rounded-md bg-oxford px-3 py-2 text-sm font-semibold text-white">
                Join {{ $invitation->workspace->name }}
            </button>
        </form>

        <form action="{{ route('invitations.decline', $token) }}" method="POST" class="mt-3"
            onsubmit="return confirm('Decline this invitation?')">
            @csrf
            <button type="submit" class="w-full rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-700 ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                Decline
            </button>
        </form>

        <p class="mt-6 text-xs text-gray-500">
            This invitation expires {{ $invitation->expires_at->diffForHumans() }}.
        </p>
    </x-invitations.shell>
@endsection
