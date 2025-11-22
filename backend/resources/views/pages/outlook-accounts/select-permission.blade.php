@extends('layouts.base')

@section('title', 'Select Outlook Account Permissions')

@section('content')
    <x-cards.card>
        <div>
            <h1 class="text-base font-semibold leading-6 text-gray-900">Select Account Permissions</h1>
            <p class="mt-2 text-sm text-gray-700">Choose the level of access you want to grant to your Microsoft Outlook account.</p>
        </div>

        <form action="{{ route('outlook-accounts.auth') }}" method="POST" class="mt-6" x-data="{ permissionType: 'read' }">
            @csrf
            <div class="space-y-4">
                <!-- Read Permission Option -->
                <label class="relative flex cursor-pointer rounded-lg border p-4 shadow-sm focus:outline-none transition-all" 
                       :class="permissionType === 'read' ? 'border-blue-600 bg-blue-50' : 'border-gray-300 bg-white hover:border-gray-400'">
                    <input type="radio" name="permission_type" value="read" class="sr-only" x-model="permissionType" checked>
                    <span class="flex flex-1">
                        <span class="flex flex-col">
                            <span class="block text-sm font-medium" :class="permissionType === 'read' ? 'text-blue-900' : 'text-gray-900'">Read Only</span>
                            <span class="mt-1 flex items-center text-sm" :class="permissionType === 'read' ? 'text-blue-700' : 'text-gray-500'">
                                View calendar events and room availability. Cannot create or modify events.
                            </span>
                        </span>
                    </span>
                    <svg class="h-5 w-5 flex-shrink-0" :class="permissionType === 'read' ? 'text-blue-600' : 'text-gray-400'" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a1 1 0 00-1.714-1.382L9 9.586 7.857 8.809a1 1 0 00-1.714 1.382l2 2.5a1 1 0 001.428 0l4-5z" clip-rule="evenodd" />
                    </svg>
                    <span class="pointer-events-none absolute -inset-px rounded-lg border-2" aria-hidden="true" :class="permissionType === 'read' ? 'border-blue-600' : 'border-transparent'"></span>
                </label>

                <!-- Write Permission Option -->
                <label class="relative flex cursor-pointer rounded-lg border p-4 shadow-sm focus:outline-none transition-all" 
                       :class="permissionType === 'write' ? 'border-blue-600 bg-blue-50' : 'border-gray-300 bg-white hover:border-gray-400'">
                    <input type="radio" name="permission_type" value="write" class="sr-only" x-model="permissionType">
                    <span class="flex flex-1">
                        <span class="flex flex-col">
                            <span class="block text-sm font-medium" :class="permissionType === 'write' ? 'text-blue-900' : 'text-gray-900'">
                                Read & Write
                                <span class="ml-2 inline-flex items-center rounded-md bg-blue-50 px-2 py-1 text-xs font-medium text-blue-700 ring-1 ring-inset ring-blue-600/20">Recommended</span>
                            </span>
                            <span class="mt-1 flex items-center text-sm" :class="permissionType === 'write' ? 'text-blue-700' : 'text-gray-500'">
                                View calendar events and create new bookings. <strong class="font-semibold" :class="permissionType === 'write' ? 'text-blue-900' : 'text-gray-900'">Required for ad-hoc room bookings</strong> when users book rooms directly from the tablet display.
                            </span>
                        </span>
                    </span>
                    <svg class="h-5 w-5 flex-shrink-0" :class="permissionType === 'write' ? 'text-blue-600' : 'text-gray-400'" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a1 1 0 00-1.714-1.382L9 9.586 7.857 8.809a1 1 0 00-1.714 1.382l2 2.5a1 1 0 001.428 0l4-5z" clip-rule="evenodd" />
                    </svg>
                    <span class="pointer-events-none absolute -inset-px rounded-lg border-2" aria-hidden="true" :class="permissionType === 'write' ? 'border-blue-600' : 'border-transparent'"></span>
                </label>
            </div>

            <div class="mt-6 flex items-center justify-end gap-x-3">
                <a href="{{ route('dashboard') }}" class="text-sm font-semibold leading-6 text-gray-900">Cancel</a>
                <button type="submit" class="rounded-md bg-blue-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600">
                    Continue to Microsoft
                </button>
            </div>
        </form>
    </x-cards.card>
@endsection

