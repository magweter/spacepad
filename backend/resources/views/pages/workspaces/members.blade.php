@extends('layouts.base')
@section('title', 'Manage workspace')

@section('content')
    <x-alerts.alert />

    {{-- Workspace name --}}
    <x-cards.card class="mb-6">
        <h2 class="text-base font-semibold text-gray-900 mb-1">Workspace</h2>
        <p class="text-sm text-gray-500 mb-4">
            Everyone in this workspace manages the same displays, boards and calendar accounts.
        </p>

        @can('update', $workspace)
            <form action="{{ route('workspaces.update', $workspace) }}" method="POST" class="flex gap-2 items-start max-w-lg">
                @csrf
                @method('PATCH')
                <div class="flex-1">
                    <label for="workspace_name" class="sr-only">Workspace name</label>
                    <input type="text" id="workspace_name" name="name" value="{{ $workspace->name }}"
                        class="block w-full px-3 py-2 border rounded-md border-gray-300 focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                    @error('name')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>
                <button type="submit"
                    class="rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                    Rename
                </button>
            </form>
        @else
            <p class="text-sm font-medium text-gray-900">{{ $workspace->name }}</p>
        @endcan
    </x-cards.card>

    {{-- Subscription. Lives here rather than on the account page: the subscription belongs to
         the workspace, and owners and admins are the ones who act on it. --}}
    <x-cards.card class="mb-6">
        @php
            // Decided once, then used twice below: for the note and for the button that goes
            // with it. Two separate condition chains would drift apart, and someone would end
            // up with a button their workspace has no use for.
            $isSelfHosted = config('settings.is_self_hosted');
            $billingState = match (true) {
                ! auth()->user()->can('manageBilling', $workspace) => $workspace->hasPro() ? 'others' : 'ask',
                // We invoice a manually billed workspace ourselves and an unlimited one is not
                // charged at all, so neither has a subscription to open or buy.
                $workspace->is_manually_billed => 'manual',
                // Cloud only: on a self-hosted instance Pro comes from an instance licence,
                // which is a Lemon Squeezy subscription like any other.
                $workspace->is_unlimited && ! $isSelfHosted => 'unlimited',
                $workspace->hasPro() => $canOpenBillingPortal ? 'portal' : 'modal',
                ! $isSelfHosted => 'checkout',
                default => null,
            };
        @endphp

        <h2 class="text-base font-semibold text-gray-900 mb-1">Subscription</h2>
        <p class="text-sm text-gray-500 mb-4">
            Usage for this workspace. Displays count once, boards count double. There is no charge
            per team member.
        </p>

        {{-- The figures only, without the line-by-line arithmetic that used to repeat them
             underneath: the sentence above already says how the units are counted. --}}
        <div class="bg-gray-50 rounded-lg p-4">
            <dl class="grid grid-cols-2 gap-4 {{ $monthlyCost !== null ? 'sm:grid-cols-4' : 'sm:grid-cols-3' }}">
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
                @if($monthlyCost !== null)
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Total billed</dt>
                        <dd class="mt-1 text-2xl font-semibold text-gray-900">
                            {{ $currencySymbol }}{{ number_format($monthlyCost, 2) }}
                            <span class="text-sm font-normal whitespace-nowrap text-gray-500">per month</span>
                        </dd>
                    </div>
                @endif
            </dl>
        </div>

        {{-- Trial countdown. A trial is an ordinary subscription that Lemon Squeezy converts on
             its own, so this states when that happens rather than asking anyone to buy: a second
             checkout would create a second subscription and bill the workspace twice. --}}
        @if($subscription?->onTrial() && $subscription->trial_ends_at)
            @php $trialDaysLeft = (int) ceil(now()->diffInDays($subscription->trial_ends_at, false)) @endphp
            <div class="mt-4 rounded-lg bg-blue-50 border border-blue-100 p-4">
                <p class="text-sm font-semibold text-blue-900">
                    @if($trialDaysLeft <= 0)
                        Your trial ends today
                    @elseif($trialDaysLeft === 1)
                        1 day left of your trial
                    @else
                        {{ $trialDaysLeft }} days left of your trial
                    @endif
                </p>
                <p class="text-sm text-blue-800 mt-1">
                    Your subscription starts automatically on
                    {{ $subscription->trial_ends_at->format('j F Y') }}. Nothing to do, and your
                    displays keep running.
                </p>
            </div>
        @endif

        {{-- One action bar, so there is a single obvious place to look for whatever this
             workspace can do about its subscription. --}}
        @if($billingState !== null)
            <div class="mt-5 pt-4 border-t border-gray-100 sm:flex sm:items-center sm:justify-between sm:gap-4">
                <p class="text-sm text-gray-500 sm:max-w-xl">
                    @switch($billingState)
                        @case('manual')
                            We invoice this workspace directly at the agreed price, so there is no
                            subscription to manage here. For anything about your invoice, email
                            <a href="mailto:support@spacepad.io" class="text-blue-600 hover:text-blue-700">support@spacepad.io</a>.
                            @break
                        @case('unlimited')
                            This workspace has Pro at no charge.
                            @break
                        @case('portal')
                            Change your payment method, download invoices or manage your
                            subscription in the Lemon Squeezy billing portal.
                            @break
                        @case('modal')
                            Your subscription is managed in Lemon Squeezy.
                            @break
                        @case('checkout')
                            Pro unlocks multiple displays, boards, check-in, personalisation and
                            inviting colleagues.
                            @break
                        @case('others')
                            This workspace is on Pro. Only owners and admins can change the subscription.
                            @break
                        @case('ask')
                            Ask an owner or admin of this workspace to upgrade to Pro.
                            @break
                    @endswitch
                </p>

                @if($billingState === 'portal')
                    {{-- Posted rather than linked: the portal url comes from a live Lemon Squeezy
                         call, so a link here would hit their API on every view of this page. It
                         opens in its own tab so nobody loses the page they were working on. --}}
                    <form action="{{ route('billing.portal') }}" method="POST" target="_blank" rel="noopener"
                        class="mt-3 sm:mt-0 shrink-0">
                        @csrf
                        <button type="submit"
                            class="inline-flex items-center gap-2 rounded-md bg-oxford px-3 py-2 text-sm font-semibold text-white shadow-sm hover:opacity-90">
                            Open billing portal
                            <x-icons.external class="h-4 w-4" />
                        </button>
                    </form>
                @elseif($billingState === 'modal')
                    {{-- No customer record to open a portal for: a self-hosted instance licence, or
                         a workspace whose customer row stayed on the user. The modal points them at
                         the link in their Lemon Squeezy emails instead. --}}
                    <div class="mt-3 sm:mt-0 shrink-0">
                        <button type="button"
                            onclick="window.dispatchEvent(new CustomEvent('open-modal', { detail: 'manage-subscription' }))"
                            class="inline-flex items-center rounded-md bg-oxford px-3 py-2 text-sm font-semibold text-white shadow-sm hover:opacity-90">
                            Manage subscription
                        </button>
                    </div>
                @elseif($billingState === 'checkout')
                    {{-- Posted rather than built while rendering: the vendor's Checkout::url() calls
                         the Lemon Squeezy API, which would run on every page view. --}}
                    <form action="{{ route('billing.checkout') }}" method="POST" class="mt-3 sm:mt-0 shrink-0">
                        @csrf
                        <button type="submit"
                            class="inline-flex items-center rounded-md bg-oxford px-3 py-2 text-sm font-semibold text-white shadow-sm hover:opacity-90">
                            Upgrade to Pro
                        </button>
                    </form>
                @endif
            </div>
        @endif
    </x-cards.card>

    {{-- Members --}}
    <x-cards.card class="mb-6">
        <div class="sm:flex sm:items-center mb-4">
            <div class="sm:flex-auto">
                <h2 class="text-base font-semibold text-gray-900 mb-1">Members</h2>
                <p class="text-sm text-gray-500">
                    Owners and admins manage billing and invitations. Only owners can change roles or
                    remove members. Everyone can manage displays and boards.
                </p>
            </div>
        </div>

        <div class="flow-root">
            <table class="min-w-full divide-y divide-gray-300">
                <thead>
                    <tr>
                        <th scope="col" class="py-3.5 pr-3 text-left text-sm font-semibold text-gray-900">Name</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Email</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Role</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Member since</th>
                        <th scope="col" class="relative py-3.5 pl-3"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @foreach($members as $member)
                        @php
                            $memberRole = \App\Enums\WorkspaceRole::fromPivot($member->pivot->role);
                            $isSelf = $member->id === auth()->id();
                        @endphp
                        <tr>
                            <td class="whitespace-nowrap py-4 pr-3 text-sm font-medium text-gray-900">
                                {{ $member->name }}
                                @if($isSelf)
                                    <span class="ml-1 inline-flex items-center rounded-md bg-gray-100 px-1.5 py-0.5 text-xs font-medium text-gray-600">You</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">{{ $member->email }}</td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                                @if(!$isSelf && auth()->user()->can('updateMemberRole', $workspace))
                                    <form action="{{ route('workspaces.members.update', $member->pivot->id) }}" method="POST">
                                        @csrf
                                        @method('PATCH')
                                        <select name="role" onchange="this.form.submit()"
                                            class="rounded-md border border-gray-300 px-2 py-1 text-sm focus:border-blue-500 focus:ring-blue-500">
                                            @foreach(\App\Enums\WorkspaceRole::cases() as $role)
                                                <option value="{{ $role->value }}" {{ $memberRole === $role ? 'selected' : '' }}>
                                                    {{ $role->label() }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </form>
                                @else
                                    {{ $memberRole->label() }}
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                                {{ $member->pivot->created_at?->format('d M Y') ?? '-' }}
                            </td>
                            <td class="relative whitespace-nowrap py-4 pl-3 text-right text-sm">
                                @if(!$isSelf && auth()->user()->can('removeMember', $workspace))
                                    <form action="{{ route('workspaces.members.destroy', $member->pivot->id) }}" method="POST"
                                        onsubmit="return confirm('Remove {{ $member->email }} from this workspace? Their displays and boards stay in the workspace.')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-red-600 hover:text-red-900">Remove</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-cards.card>

    {{-- Pending invitations --}}
    @can('revokeInvitation', $workspace)
        <x-cards.card class="mb-6">
            <h2 class="text-base font-semibold text-gray-900 mb-1">Pending invitations</h2>
            <p class="text-sm text-gray-500 mb-4">
                Invitations expire after {{ \App\Models\WorkspaceInvitation::LIFETIME_DAYS }} days.
            </p>

            @if($invitations->isEmpty())
                <p class="text-sm text-gray-500">No pending invitations.</p>
            @else
                <table class="min-w-full divide-y divide-gray-300">
                    <thead>
                        <tr>
                            <th scope="col" class="py-3.5 pr-3 text-left text-sm font-semibold text-gray-900">Email</th>
                            <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Role</th>
                            <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Invited by</th>
                            <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Expires</th>
                            <th scope="col" class="relative py-3.5 pl-3"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach($invitations as $invitation)
                            <tr>
                                <td class="whitespace-nowrap py-4 pr-3 text-sm font-medium text-gray-900">{{ $invitation->email }}</td>
                                <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">{{ $invitation->role->label() }}</td>
                                <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">{{ $invitation->invitedBy?->name ?? '-' }}</td>
                                <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">{{ $invitation->expires_at->diffForHumans() }}</td>
                                <td class="relative whitespace-nowrap py-4 pl-3 text-right text-sm space-x-3">
                                    @can('invite', $workspace)
                                        <form action="{{ route('workspaces.invitations.resend', $invitation) }}" method="POST" class="inline">
                                            @csrf
                                            <button type="submit" class="text-blue-600 hover:text-blue-900">Resend</button>
                                        </form>
                                    @endcan
                                    <form action="{{ route('workspaces.invitations.destroy', $invitation) }}" method="POST" class="inline"
                                        onsubmit="return confirm('Withdraw the invitation for {{ $invitation->email }}?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-red-600 hover:text-red-900">Withdraw</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </x-cards.card>
    @endcan

    {{-- Invite --}}
    @can('invite', $workspace)
        <x-cards.card class="mb-6">
            <h2 class="text-base font-semibold text-gray-900 mb-1">Invite a colleague</h2>
            <p class="text-sm text-gray-500 mb-4">
                They receive an email with a link that signs them in and adds them to this workspace.
                There is no per-user charge, billing is based on your displays and boards.
            </p>

            <form action="{{ route('workspaces.invitations.store') }}" method="POST" class="flex flex-col sm:flex-row gap-2 sm:items-start">
                @csrf
                <div class="flex-1">
                    <label for="invite_email" class="sr-only">Email address</label>
                    <input type="email" id="invite_email" name="email" value="{{ old('email') }}" required
                        placeholder="colleague@company.com"
                        class="block w-full px-3 py-2 border rounded-md border-gray-300 focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                    @error('email')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="invite_role" class="sr-only">Role</label>
                    <select id="invite_role" name="role"
                        class="block w-full px-3 py-2 border rounded-md border-gray-300 focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                        <option value="member">Member: manages displays and boards</option>
                        <option value="admin">Admin: can also invite colleagues</option>
                    </select>
                    @error('role')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>
                <button type="submit"
                    class="rounded-md bg-oxford px-3 py-2 text-sm font-semibold text-white">
                    Send invitation
                </button>
            </form>
        </x-cards.card>
    @elseif(!$canInvite)
        <x-cards.card class="mb-6">
            <h2 class="text-base font-semibold text-gray-900 mb-1">Invite a colleague</h2>
            <p class="text-sm text-gray-500">
                Team collaboration is a Pro feature.
                @if(auth()->user()->can('manageBilling', $workspace))
                    Upgrade to Pro to invite colleagues into this workspace.
                @else
                    Ask an owner or admin of this workspace to upgrade to Pro.
                @endif
            </p>
        </x-cards.card>
    @endcan

    {{-- Delete an empty leftover workspace --}}
    @can('delete', $workspace)
        <x-cards.card class="border border-red-200 bg-red-50 mb-6">
            <h2 class="text-base font-semibold text-red-900 mb-1">Delete this workspace</h2>
            <p class="text-sm text-red-700 mb-4">
                This workspace is empty and you are its only member. Deleting it is useful if you
                moved everything into another workspace and no longer need this one.
            </p>

            <form action="{{ route('workspaces.destroy', $workspace) }}" method="POST"
                x-data="{ open: false }"
                @submit.prevent="if(open) $el.submit(); else open = true">
                @csrf
                @method('DELETE')

                <div x-show="open" x-cloak class="mb-4 max-w-sm">
                    <label for="confirm_name" class="block text-sm font-medium text-red-800 mb-1">
                        Type <span class="font-mono">{{ $workspace->name }}</span> to confirm:
                    </label>
                    <input type="text" id="confirm_name" name="confirm_name" autocomplete="off"
                        class="mt-1 px-3 py-2 block w-full border rounded-md border-red-300 focus:border-red-500 focus:ring-red-500 sm:text-sm bg-white">
                    @error('confirm_name')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit"
                    :class="open ? 'bg-red-600 hover:bg-red-700 text-white' : 'bg-white border border-red-400 text-red-700 hover:bg-red-100'"
                    class="rounded-md px-3 py-2 text-sm font-semibold shadow-sm">
                    <span x-text="open ? 'Permanently delete this workspace' : 'Delete workspace'">Delete workspace</span>
                </button>
            </form>
        </x-cards.card>
    @endcan

    {{-- Leave --}}
    @if($members->count() > 1)
        <x-cards.card class="border border-red-200 bg-red-50">
            <h2 class="text-base font-semibold text-red-900 mb-1">Leave this workspace</h2>
            <p class="text-sm text-red-700 mb-4">
                You lose access to its displays, boards and calendar accounts. Nothing is deleted.
            </p>

            <form action="{{ route('workspaces.leave') }}" method="POST"
                onsubmit="return confirm('Leave {{ $workspace->name }}?')">
                @csrf

                @if($myRole === \App\Enums\WorkspaceRole::OWNER && $workspace->owners()->count() <= 1)
                    <div class="mb-3 max-w-sm">
                        <label for="successor_user_id" class="block text-sm font-medium text-red-800 mb-1">
                            You are the only owner. Choose who takes over:
                        </label>
                        <select id="successor_user_id" name="successor_user_id" required
                            class="block w-full px-3 py-2 border rounded-md border-red-300 focus:border-red-500 focus:ring-red-500 sm:text-sm bg-white">
                            @foreach($members->where('id', '!=', auth()->id()) as $candidate)
                                <option value="{{ $candidate->id }}">{{ $candidate->name }} ({{ $candidate->email }})</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                <button type="submit"
                    class="rounded-md bg-white border border-red-400 px-3 py-2 text-sm font-semibold text-red-700 hover:bg-red-100">
                    Leave workspace
                </button>
            </form>
        </x-cards.card>
    @endif
@endsection
