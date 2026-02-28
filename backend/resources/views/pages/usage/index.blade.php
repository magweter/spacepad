@extends('layouts.base')
@section('title', 'Usage & Billing')

@section('content')
    <x-cards.card>
        <div class="space-y-6">
            {{-- Usage Breakdown --}}
            <div>
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Current Usage</h2>
                <div class="bg-gray-50 rounded-lg p-6">
                    <dl class="grid grid-cols-1 gap-6 sm:grid-cols-3">
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Displays</dt>
                            <dd class="mt-1 text-3xl font-semibold text-gray-900">{{ $usageBreakdown['displays'] }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Boards</dt>
                            <dd class="mt-1 text-3xl font-semibold text-gray-900">{{ $usageBreakdown['boards'] }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Total Usage</dt>
                            <dd class="mt-1 text-3xl font-semibold text-gray-900">{{ $usageBreakdown['total'] }}</dd>
                        </div>
                    </dl>
                </div>
            </div>

            {{-- Breakdown Details --}}
            <div>
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Usage Breakdown</h2>
                <div class="space-y-4">
                    <div class="flex items-center justify-between p-4 bg-white border border-gray-200 rounded-lg">
                        <div>
                            <div class="text-sm font-medium text-gray-900">Displays</div>
                            <div class="text-sm text-gray-500">{{ $usageBreakdown['displays'] }} active display(s)</div>
                        </div>
                        <div class="text-lg font-semibold text-gray-900">{{ $usageBreakdown['displays'] }} unit(s)</div>
                    </div>
                    <div class="flex items-center justify-between p-4 bg-white border border-gray-200 rounded-lg">
                        <div>
                            <div class="text-sm font-medium text-gray-900">Boards</div>
                            <div class="text-sm text-gray-500">{{ $usageBreakdown['boards'] }} board(s) × 2</div>
                        </div>
                        <div class="text-lg font-semibold text-gray-900">{{ $usageBreakdown['board_usage'] }} unit(s)</div>
                    </div>
                    <div class="flex items-center justify-between p-4 bg-blue-50 border border-blue-200 rounded-lg">
                        <div>
                            <div class="text-sm font-medium text-blue-900">Total Usage</div>
                            <div class="text-sm text-blue-700">Billed to your subscription</div>
                        </div>
                        <div class="text-2xl font-bold text-blue-900">{{ $usageBreakdown['total'] }} unit(s)</div>
                    </div>
                </div>
            </div>

            {{-- What Counts Towards Usage --}}
            <div>
                <h2 class="text-lg font-semibold text-gray-900 mb-4">What counts towards usage?</h2>
                <div class="space-y-3">
                    <div class="flex items-start gap-3">
                        <div class="flex-shrink-0">
                            <div class="flex h-8 w-8 items-center justify-center rounded-full bg-green-100">
                                <svg class="h-5 w-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                        </div>
                        <div>
                            <div class="text-sm font-medium text-gray-900">Displays</div>
                            <div class="text-sm text-gray-600">Each active display counts as <strong>1 usage unit</strong>. Every display in your workspace is included in your usage count.</div>
                        </div>
                    </div>
                    <div class="flex items-start gap-3">
                        <div class="flex-shrink-0">
                            <div class="flex h-8 w-8 items-center justify-center rounded-full bg-blue-100">
                                <svg class="h-5 w-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                        </div>
                        <div>
                            <div class="text-sm font-medium text-gray-900">Boards</div>
                            <div class="text-sm text-gray-600">Each board counts as <strong>2 usage units</strong>. This pricing model ensures fairness for users who don't use boards, keeping the base product accessible.</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </x-cards.card>
@endsection
