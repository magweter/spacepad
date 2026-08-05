@extends('layouts.base')
@section('title', 'Workspace - ' . $workspace->name)
@section('container_class', 'max-w-4xl')

{{-- Laid out like the user detail page, because it is the same kind of screen: a drill-down
     from a list, not one of the admin tabs. Hence the narrower container, the card, and the
     link back up instead of the tab bar. --}}
@section('content')
    <x-cards.card>
        <div class="sm:flex sm:items-center mb-6">
            <div class="sm:flex-auto">
                <h1 class="text-lg font-semibold leading-6 text-gray-900">{{ $workspace->name }}</h1>
                <p class="mt-1 text-sm text-gray-500">Usage, billing and members for this workspace</p>
            </div>
            <div class="mt-4 sm:ml-16 sm:mt-0">
                <a href="{{ route('admin.workspaces.index') }}" class="text-sm font-medium text-blue-600 hover:text-blue-500">
                    Back to Workspaces
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
                <h3 class="text-base font-semibold text-gray-900 mb-4">Workspace</h3>
                <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Workspace ID</dt>
                        <dd class="mt-1 text-sm text-gray-900 font-mono">{{ $workspace->id }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Created</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $workspace->created_at?->format('d M Y H:i') }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Billing owner</dt>
                        <dd class="mt-1 text-sm text-gray-900">
                            @php $owner = $workspace->billingOwner(); @endphp
                            @if($owner)
                                <a href="{{ route('admin.users.show', $owner) }}" class="text-blue-600 hover:text-blue-500">
                                    {{ $owner->name }} ({{ $owner->email }})
                                </a>
                            @else
                                <span class="text-gray-400">Nobody</span>
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Plan</dt>
                        <dd class="mt-1 text-sm text-gray-900">
                            @if($workspace->is_unlimited)
                                Unlimited
                            @elseif($workspace->is_manually_billed)
                                Manually billed
                            @elseif($workspace->hasPro())
                                Pro
                            @else
                                Free
                            @endif
                        </dd>
                    </div>
                </dl>
            </div>

            <div class="border border-gray-200 rounded-lg p-6">
                <h3 class="text-base font-semibold text-gray-900 mb-4">Billable usage</h3>
                <dl class="grid grid-cols-3 gap-4 mb-4">
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Displays</dt>
                        <dd class="mt-1 text-2xl font-semibold text-gray-900">{{ $usage['displays'] }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Boards</dt>
                        <dd class="mt-1 text-2xl font-semibold text-gray-900">{{ $usage['boards'] }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Total units</dt>
                        <dd class="mt-1 text-2xl font-semibold text-blue-700">{{ $usage['total'] }}</dd>
                    </div>
                </dl>
                <p class="text-xs text-gray-500">
                    {{ $usage['displays'] }} display(s) &times; 1, plus {{ $usage['boards'] }} board(s) &times; 2
                    = {{ $usage['total'] }} unit(s). Read from the workspace's usage counters, which are the
                    same figures the subscription is sized on.
                </p>
            </div>

            @if($analyticsRow)
                <div class="border border-gray-200 rounded-lg p-6">
                    <h3 class="text-base font-semibold text-gray-900 mb-4">Subscription</h3>
                    <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Status</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ Str::headline($analyticsRow->subscription_status) }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">MRR</dt>
                            <dd class="mt-1 text-sm text-gray-900">${{ number_format((float) $analyticsRow->mrr_current, 2) }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Billing interval</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $analyticsRow->billing_interval ?? '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Subscription ends</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $analyticsRow->subscription_ends_at ?? '-' }}</dd>
                        </div>
                    </dl>
                </div>
            @endif

            <div class="border border-gray-200 rounded-lg p-6">
                <h3 class="text-base font-semibold text-gray-900 mb-1">Billing</h3>
                <p class="text-sm text-gray-500 mb-4">
                    A manually billed workspace is invoiced through our own accounting instead of Lemon Squeezy.
                    It gets Pro without a subscription, and its MRR is computed here from usage.
                </p>

                <form action="{{ route('admin.workspaces.billing', $workspace) }}" method="POST" class="space-y-4">
                    @csrf

                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="is_manually_billed" value="1"
                               @checked(old('is_manually_billed', $workspace->is_manually_billed))
                               class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        <span class="text-sm text-gray-900">Manually billed (Pro without Lemon Squeezy)</span>
                    </label>

                    <div>
                        <label for="manual_billing_unit_price" class="block text-sm font-medium text-gray-700 mb-1">
                            Monthly price per unit
                        </label>
                        <div class="relative rounded-md shadow-sm max-w-xs">
                            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                <span class="text-gray-500 sm:text-sm">$</span>
                            </div>
                            <input type="number" step="0.01" min="0" name="manual_billing_unit_price"
                                   id="manual_billing_unit_price"
                                   value="{{ old('manual_billing_unit_price', $workspace->manual_billing_unit_price) }}"
                                   placeholder="{{ $globalUnitPrice ?? '0.00' }}"
                                   class="block w-full rounded-md border border-gray-300 pl-7 pr-3 py-2 text-sm text-gray-900 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500">
                        </div>
                        <p class="mt-1 text-xs text-gray-500">
                            Leave empty to use the global default (${{ number_format((float) ($globalUnitPrice ?? 0), 2) }}
                            from <code>UNIT_PRICE</code>).
                            Effective MRR: ${{ number_format($unitPrice, 2) }} &times;
                            {{ $billableUnits }} {{ Str::plural('unit', $billableUnits) }} =
                            ${{ number_format($unitPrice * $billableUnits, 2) }}.
                        </p>
                        @error('manual_billing_unit_price')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="billing_owner_user_id" class="block text-sm font-medium text-gray-700 mb-1">
                            Billing owner
                        </label>
                        <select name="billing_owner_user_id" id="billing_owner_user_id"
                                class="block w-full max-w-xs rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-900 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500">
                            @foreach($workspace->members as $member)
                                <option value="{{ $member->id }}"
                                    @selected(old('billing_owner_user_id', $workspace->billingOwner()?->id) === $member->id)>
                                    {{ $member->name }} ({{ $member->email }})
                                </option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs text-gray-500">Who Lemon Squeezy invoices, and who we contact about it.</p>
                        @error('billing_owner_user_id')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <button type="submit"
                            class="rounded-md bg-oxford px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-opacity-90">
                        Save billing settings
                    </button>
                </form>
            </div>

            @if($billingChanges->isNotEmpty())
                <div class="border border-gray-200 rounded-lg p-6">
                    <h3 class="text-base font-semibold text-gray-900 mb-4">Billing changes</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead>
                                <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                    <th class="py-2 pr-3">When</th>
                                    <th class="px-3 py-2">Change</th>
                                    <th class="px-3 py-2">Units</th>
                                    <th class="px-3 py-2">Displays</th>
                                    <th class="px-3 py-2">Boards</th>
                                    <th class="px-3 py-2 text-right">MRR</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach($billingChanges as $change)
                                    <tr>
                                        <td class="py-2 pr-3 text-gray-500">{{ $change->detected_at?->format('d M Y H:i') }}</td>
                                        <td class="px-3 py-2">
                                            <span @class([
                                                'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium',
                                                'bg-green-100 text-green-700' => $change->change_type === 'increase',
                                                'bg-red-100 text-red-700' => $change->change_type !== 'increase',
                                            ])>
                                                {{ $change->license_delta > 0 ? '+' : '' }}{{ $change->license_delta }}
                                            </span>
                                        </td>
                                        <td class="px-3 py-2 text-gray-700">{{ $change->previous_license_count }} &rarr; {{ $change->new_license_count }}</td>
                                        <td class="px-3 py-2 text-gray-700">{{ $change->previous_displays_count }} &rarr; {{ $change->new_displays_count }}</td>
                                        <td class="px-3 py-2 text-gray-700">{{ $change->previous_boards_count }} &rarr; {{ $change->new_boards_count }}</td>
                                        <td class="px-3 py-2 text-right text-gray-700">
                                            @if($change->mrr_delta === null)
                                                <span class="text-gray-400" title="Waiting for the next MRR refresh">-</span>
                                            @else
                                                {{ $change->mrr_delta > 0 ? '+' : '' }}${{ number_format((float) $change->mrr_delta, 2) }}
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            <div class="border border-gray-200 rounded-lg p-6">
                <h3 class="text-base font-semibold text-gray-900 mb-4">Members ({{ $workspace->members->count() }})</h3>
                <ul class="divide-y divide-gray-100">
                    @foreach($workspace->members as $member)
                        <li class="flex items-center justify-between py-2 text-sm">
                            <a href="{{ route('admin.users.show', $member) }}" class="text-blue-600 hover:text-blue-500">
                                {{ $member->name }} ({{ $member->email }})
                            </a>
                            <span class="text-xs text-gray-500">{{ Str::headline($member->pivot->role) }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </x-cards.card>
@endsection
