@extends('layouts.base')
@section('title', 'Merge workspaces')

@section('content')
    <x-alerts.alert />

    <x-cards.card class="mb-6">
        <div class="px-6 py-5">
            <h2 class="text-base font-semibold text-gray-900 mb-1">Merge two workspaces</h2>
            <p class="text-sm text-gray-500 mb-4">
                Moves everything from the source workspace into the target. For customers who each
                signed up separately before invitations existed. This cannot be undone, so preview
                first.
            </p>

            <form action="{{ route('admin.merge.preview') }}" method="POST" class="space-y-4">
                @csrf

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="source_workspace_id" class="block text-sm font-medium text-gray-700 mb-1">
                            Source workspace (emptied)
                        </label>
                        <input type="text" id="source_workspace_id" name="source_workspace_id" required
                            value="{{ old('source_workspace_id', $source?->id) }}"
                            placeholder="Workspace ULID"
                            class="block w-full px-3 py-2 border rounded-md border-gray-300 focus:border-blue-500 focus:ring-blue-500 sm:text-sm font-mono">
                        @if($source)
                            <p class="mt-1 text-xs text-gray-500">{{ $source->name }}</p>
                        @endif
                        @error('source_workspace_id')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="target_workspace_id" class="block text-sm font-medium text-gray-700 mb-1">
                            Target workspace (kept)
                        </label>
                        <input type="text" id="target_workspace_id" name="target_workspace_id" required
                            value="{{ old('target_workspace_id', $target?->id) }}"
                            placeholder="Workspace ULID"
                            class="block w-full px-3 py-2 border rounded-md border-gray-300 focus:border-blue-500 focus:ring-blue-500 sm:text-sm font-mono">
                        @if($target)
                            <p class="mt-1 text-xs text-gray-500">{{ $target->name }}</p>
                        @endif
                        @error('target_workspace_id')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                @php $options = $options ?? ['move_members' => true, 'rename_collisions' => true, 'delete_source' => true, 'adopt_orphans' => false]; @endphp

                <fieldset class="space-y-2">
                    <legend class="text-sm font-medium text-gray-700 mb-1">Options</legend>

                    <label class="flex items-start gap-2 text-sm text-gray-700">
                        <input type="checkbox" name="move_members" value="1" @checked($options['move_members'])
                            class="mt-0.5 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        Move members across (a source owner becomes an admin; existing roles are never downgraded)
                    </label>

                    <label class="flex items-start gap-2 text-sm text-gray-700">
                        <input type="checkbox" name="rename_collisions" value="1" @checked($options['rename_collisions'])
                            class="mt-0.5 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        Rename clashing display, board and profile names to "name (source workspace)"
                    </label>

                    <label class="flex items-start gap-2 text-sm text-gray-700">
                        <input type="checkbox" name="delete_source" value="1" @checked($options['delete_source'])
                            class="mt-0.5 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        Delete the source workspace afterwards, if it ends up empty and unbilled
                    </label>

                    <label class="flex items-start gap-2 text-sm text-gray-700">
                        <input type="checkbox" name="adopt_orphans" value="1" @checked($options['adopt_orphans'])
                            class="mt-0.5 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        Adopt rows with no workspace at all (legacy data invisible to every workspace query)
                    </label>
                </fieldset>

                <button type="submit" class="rounded-md bg-oxford px-3 py-2 text-sm font-semibold text-white">
                    Preview merge
                </button>
            </form>
        </div>
    </x-cards.card>

    @if($preview)
        <x-cards.card class="border border-amber-200 bg-amber-50">
            <div class="px-6 py-5">
                <h2 class="text-base font-semibold text-amber-900 mb-1">
                    Preview: "{{ $source->name }}" &rarr; "{{ $target->name }}"
                </h2>
                <p class="text-sm text-amber-800 mb-4">Nothing has been changed yet.</p>

                <div class="bg-white rounded-lg p-4 mb-4">
                    <h3 class="text-sm font-semibold text-gray-900 mb-2">Rows that will move</h3>
                    @if(empty($preview['counts']))
                        <p class="text-sm text-gray-500">The source workspace is empty.</p>
                    @else
                        <ul class="text-sm text-gray-700 space-y-0.5">
                            @foreach($preview['counts'] as $label => $count)
                                <li>{{ $count }} &times; {{ Str::headline($label) }}</li>
                            @endforeach
                        </ul>
                    @endif
                </div>

                <div class="bg-white rounded-lg p-4 mb-4">
                    <h3 class="text-sm font-semibold text-gray-900 mb-2">Members</h3>
                    @if(empty($preview['members']))
                        <p class="text-sm text-gray-500">The source workspace has no members.</p>
                    @else
                        <ul class="text-sm text-gray-700 space-y-0.5">
                            @foreach($preview['members'] as $member)
                                <li>{{ $member['email'] }} — {{ $member['from'] }} &rarr; {{ $member['to'] }}</li>
                            @endforeach
                        </ul>
                    @endif
                </div>

                @if(!empty($preview['nameCollisions']))
                    <div class="bg-white rounded-lg p-4 mb-4">
                        <h3 class="text-sm font-semibold text-gray-900 mb-2">Name collisions</h3>
                        @foreach($preview['nameCollisions'] as $type => $names)
                            <p class="text-sm text-gray-700">{{ Str::headline($type) }}: {{ implode(', ', $names) }}</p>
                        @endforeach
                    </div>
                @endif

                @if(!empty($preview['duplicateAccounts']))
                    <div class="bg-white rounded-lg p-4 mb-4">
                        <h3 class="text-sm font-semibold text-gray-900 mb-2">Duplicate calendar accounts</h3>
                        <p class="text-xs text-gray-500 mb-2">
                            These will be moved as additional connected accounts, not merged — their OAuth
                            tokens differ and re-pointing calendars would break sync. Disconnect one by hand
                            afterwards.
                        </p>
                        @foreach($preview['duplicateAccounts'] as $type => $emails)
                            <p class="text-sm text-gray-700">{{ Str::headline($type) }}: {{ implode(', ', $emails) }}</p>
                        @endforeach
                    </div>
                @endif

                <div class="bg-white rounded-lg p-4 mb-5">
                    <h3 class="text-sm font-semibold text-gray-900 mb-2">Billing</h3>
                    <p class="text-sm text-gray-700">
                        Source has an active subscription: <strong>{{ $preview['sourceHasSubscription'] ? 'yes' : 'no' }}</strong>.
                        Target has an active subscription: <strong>{{ $preview['targetHasSubscription'] ? 'yes' : 'no' }}</strong>.
                    </p>
                    @if($preview['sourceHasSubscription'])
                        <p class="mt-2 text-sm text-red-700">
                            The source subscription is <strong>not</strong> cancelled by this merge, and the source
                            workspace will not be deleted while it is billed. Cancel it in Lemon Squeezy or
                            switch the account to manual billing yourself.
                        </p>
                    @endif
                </div>

                <form action="{{ route('admin.merge.store') }}" method="POST">
                    @csrf
                    <input type="hidden" name="source_workspace_id" value="{{ $source->id }}">
                    <input type="hidden" name="target_workspace_id" value="{{ $target->id }}">
                    @foreach($options as $key => $value)
                        @if($value)
                            <input type="hidden" name="{{ $key }}" value="1">
                        @endif
                    @endforeach

                    <div class="mb-4 max-w-sm">
                        <label for="confirm_name" class="block text-sm font-medium text-amber-900 mb-1">
                            Type <span class="font-mono">{{ $source->name }}</span> to confirm:
                        </label>
                        <input type="text" id="confirm_name" name="confirm_name" autocomplete="off" required
                            class="mt-1 px-3 py-2 block w-full border rounded-md border-amber-300 focus:border-amber-500 focus:ring-amber-500 sm:text-sm bg-white">
                        @error('confirm_name')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <button type="submit" class="rounded-md bg-red-600 px-3 py-2 text-sm font-semibold text-white hover:bg-red-700">
                        Merge workspaces
                    </button>
                </form>
            </div>
        </x-cards.card>
    @endif
@endsection
