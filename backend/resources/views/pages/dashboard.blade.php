@extends('layouts.base')
@section('title', 'Management dashboard')
@section('content')
    <!-- Session Status Alert -->
    @if(session('status'))
        <div id="alert" class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4">
            {{ session('status') }}
        </div>
    @endif

    <div class="mb-6">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
            <div class="sm:flex sm:items-center sm:col-span-2 md:col-span-3">
                <div class="sm:flex-auto">
                    <h1 class="text-lg font-semibold leading-6 text-gray-900">Accounts</h1>
                    <p class="mt-2 text-md text-gray-500">Your connected outlook accounts.</p>
                </div>
            </div>
            <div class="bg-gray-50 sm:rounded-lg sm:col-span-2 md:col-span-1">
                <div class="px-4 py-3 sm:p-5 flex">
                    <h3 class="text-base font-semibold text-gray-900">Connect code</h3>
                    <div class="max-w-xl text-base text-gray-500 ml-auto">
                        <p>{{ chunk_split($connectCode, 3, ' ') }}</p>
                    </div>
                </div>
            </div>
        </div>
        <div class="mt-4 flow-root">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                @foreach($outlookAccounts as $outlookAccount)
                    <div class="relative flex items-center space-x-3 rounded-lg border border-gray-300 bg-white px-6 py-5 shadow-sm focus-within:ring-2 focus-within:ring-blue-500 focus-within:ring-offset-2 hover:border-gray-400">
                        <div class="flex-shrink-0">
                            <x-icons.microsoft class="h-10 w-10" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <span class="absolute inset-0" aria-hidden="true"></span>
                            <div class="flex items-center gap-2">
                                <p class="text-md font-medium text-gray-900">{{ $outlookAccount->name }}</p>
                                <p class="mt-0.5 whitespace-nowrap rounded-md bg-green-50 px-1.5 py-0.5 text-sm font-medium text-green-700 ring-1 ring-inset ring-green-600/20">Connected</p>
                            </div>
                            <p class="truncate text-sm text-gray-500 flex items-center gap-2 mt-1">
                                <span>{{ $outlookAccount->email }}</span>
                                <svg viewBox="0 0 2 2" class="h-0.5 w-0.5 flex-none fill-gray-500">
                                    <circle cx="1" cy="1" r="1" />
                                </svg>
                                <span>Created on {{ $outlookAccount->created_at->toDateTimeString() }}</span>
                            </p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
        <div class="mt-4 flex gap-2">
            <a href="{{ route('outlook-accounts.auth') }}" class="text-md font-medium text-blue-600 hover:text-blue-500">
                Connect an Outlook account
                <span aria-hidden="true"> &rarr;</span>
            </a>
        </div>
    </div>

    <div class="mb-6 rounded-md bg-gray-50 p-4">
        <div class="flex">
            <div class="flex-1 md:flex md:justify-between">
                <p class="text-base text-gray-700">Connect a new tablet by downloading the app from the <a target="_blank" href="https://play.google.com/store/apps/details?id=com.magweter.spacepad" class="text-blue-600 hover:text-blue-500">Play Store</a> or <a target="_blank" href="https://apps.apple.com/nl/app/spacepad/id6745528995" class="text-blue-600 hover:text-blue-500">App Store</a> and using the connect code displayed in the top right corner.</p>
            </div>
        </div>
    </div>

    <div>
        <div class="sm:flex sm:items-center">
            <div class="sm:flex-auto">
                <h1 class="text-lg font-semibold leading-6 text-gray-900">Displays</h1>
                <p class="mt-2 text-md text-gray-500">Your displays and their status.</p>
            </div>
            <div class="mt-4 sm:ml-16 sm:mt-0 sm:flex-none">
                <a href="{{ route('displays.create') }}" class="block rounded-md bg-oxford px-3 py-2 text-center text-md font-semibold text-white">Create new display</a>
            </div>
        </div>
        <div class="mt-4 flow-root">
            <div class="-mx-4 -my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
                <div class="inline-block min-w-full py-2 align-middle sm:px-6 lg:px-8">
                    <table class="min-w-full divide-y divide-gray-300">
                        <thead>
                        <tr>
                            <th scope="col" class="py-3.5 pl-4 pr-3 text-left text-md font-semibold text-gray-900 sm:pl-0" style="width: 15%">Device name</th>
                            <th scope="col" class="py-3.5 pl-4 pr-3 text-left text-md font-semibold text-gray-900 sm:pl-0" style="width: 15%">Display name</th>
                            <th scope="col" class="px-3 py-3.5 text-left text-md font-semibold text-gray-900">Account</th>
                            <th scope="col" class="px-3 py-3.5 text-left text-md font-semibold text-gray-900">Room</th>
                            <th scope="col" class="px-3 py-3.5 text-left text-md font-semibold text-gray-900">Status</th>
                            <th scope="col" class="px-3 py-3.5 text-left text-md font-semibold text-gray-900">Devices</th>
                            <th scope="col" class="relative py-3.5 pl-3 pr-4 sm:pr-0"></th>
                        </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                        @foreach($displays as $display)
                            <tr>
                                <td class="whitespace-nowrap py-4 pl-4 pr-3 text-md font-medium text-gray-900 sm:pl-0">{{ $display->name }}</td>
                                <td class="whitespace-nowrap py-4 pl-4 pr-3 text-md font-medium text-gray-900 sm:pl-0">{{ $display->display_name }}</td>
                                <td class="whitespace-nowrap px-3 py-4 text-md text-gray-500">
                                    <div class="flex items-center">
                                        <x-icons.microsoft class="mr-2 size-4 text-muted-foreground inline-flex" />
                                        <span>{{ $display->calendar->outlookAccount->name }}</span>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-3 py-4 text-md text-gray-500">
                                    <div class="flex items-center">
                                        <span>{{ $display->calendar->name }}</span>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-3 py-4 text-md text-gray-500">
                                    @if ($display->status === \App\Enums\DisplayStatus::READY)
                                        <p class="inline-flex whitespace-nowrap rounded-md bg-blue-50 px-1.5 py-0.5 text-sm font-medium text-blue-700 ring-1 ring-inset ring-blue-600/20">Ready</p>
                                    @elseif ($display->status === \App\Enums\DisplayStatus::ACTIVE)
                                        <p class="inline-flex whitespace-nowrap rounded-md bg-green-50 px-1.5 py-0.5 text-sm font-medium text-green-700 ring-1 ring-inset ring-green-600/20">Active</p>
                                    @elseif ($display->status === \App\Enums\DisplayStatus::DEACTIVATED)
                                        <p class="inline-flex whitespace-nowrap rounded-md bg-gray-50 px-1.5 py-0.5 text-sm font-medium text-gray-700 ring-1 ring-inset ring-gray-600/20">Disabled</p>
                                    @elseif ($display->status === \App\Enums\DisplayStatus::ERROR)
                                        <p class="inline-flex whitespace-nowrap rounded-md bg-red-50 px-1.5 py-0.5 text-sm font-medium text-red-700 ring-1 ring-inset ring-red-600/20">Error - We'll get back to you asap!</p>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-3 py-4 text-md text-gray-500">
                                    @forelse($display->devices as $device)
                                        {{ $device->name }}<br/>
                                    @empty
                                        -
                                    @endforelse
                                </td>
                                <td class="relative whitespace-nowrap py-4 pl-3 pr-4 text-right text-md font-medium sm:pr-0 flex justify-end gap-3">
                                    <form action="{{ route('displays.updateStatus', $display) }}" method="POST">
                                        @csrf
                                        @method('PATCH')
                                        @if ($display->status === \App\Enums\DisplayStatus::ACTIVE)
                                            <input type="hidden" name="status" value="{{\App\Enums\DisplayStatus::DEACTIVATED}}" />
                                            <button type="submit" class="text-blue-600 hover:text-blue-900">Deactivate</button>
                                        @elseif ($display->status === \App\Enums\DisplayStatus::DEACTIVATED)
                                            <input type="hidden" name="status" value="{{\App\Enums\DisplayStatus::ACTIVE}}" />
                                            <button type="submit" class="text-blue-600 hover:text-blue-900">Activate</button>
                                        @else
                                            <button type="submit" class="text-gray-400" disabled>Deactivate</button>
                                        @endif
                                    </form>
                                    <form action="{{ route('displays.delete', $display) }}" method="POST">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-red-600 hover:text-red-900">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
