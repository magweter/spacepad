@extends('layouts.base')
@section('title', 'Management dashboard')
@section('content')
    <!-- Session Status Alert -->
    <x-alerts.alert />

    @if(! auth()->user()->hasAccess())
        <div class="rounded-md bg-yellow-50 p-4 mb-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3 flex-1 md:flex md:justify-between">
                    <p class="text-yellow-700">Your subscription has expired. Please renew to continue using Spacepad.</p>
                    <p class="mt-3 md:ml-6 md:mt-0">
                        <x-lemon-button :href="$checkout" class="whitespace-nowrap font-medium text-yellow-700 hover:text-yellow-600">
                            Subscribe now
                            <span aria-hidden="true"> &rarr;</span>
                        </x-lemon-button>
                    </p>
                </div>
            </div>
        </div>
    @else
        <div class="mb-6 flex gap-4">
            <div class="rounded-md bg-gray-50 p-4 grow">
                <div class="flex">
                    <div class="flex-1 md:flex md:justify-between">
                        <p class="text-base text-gray-700"><strong>You're all set!</strong> Connect a new device with the app from the <a target="_blank" href="https://play.google.com/store/apps/details?id=com.magweter.spacepad" class="text-blue-600 hover:text-blue-500">Play Store</a> or <a target="_blank" href="https://apps.apple.com/nl/app/spacepad/id6745528995" class="text-blue-600 hover:text-blue-500">App Store</a> and using the connect code.</p>
                    </div>
                </div>
            </div>
            <div class="bg-gray-50 rounded-lg items-center flex">
                <div class="p-4 flex w-full">
                    <h3 class="text-base font-semibold text-gray-900 mr-8">Connect code</h3>
                    <div class="max-w-xl text-base text-gray-500 ml-auto">
                        <p>{{ chunk_split($connectCode, 3, ' ') }}</p>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <div class="mb-8">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
            <div class="sm:flex sm:items-center sm:col-span-2 md:col-span-3">
                <div class="sm:flex-auto">
                    <h1 class="text-lg font-semibold leading-6 text-gray-900">Accounts</h1>
                    <p class="mt-1 text-md text-gray-500">The accounts used to connect to calendars and rooms.</p>
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
                                <p class="mt-0.5 whitespace-nowrap rounded-md bg-{{ $outlookAccount->status->color() }}-50 px-1.5 py-0.5 text-xs font-medium text-{{ $outlookAccount->status->color() }}-700 ring-1 ring-inset ring-{{ $outlookAccount->status->color() }}-600/20">{{ $outlookAccount->status->label() }}</p>
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
                @foreach($googleAccounts as $googleAccount)
                    <div class="relative flex items-center space-x-3 rounded-lg border border-gray-300 bg-white px-6 py-5 shadow-sm focus-within:ring-2 focus-within:ring-blue-500 focus-within:ring-offset-2 hover:border-gray-400">
                        <div class="flex-shrink-0">
                            <x-icons.google class="h-10 w-10" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <span class="absolute inset-0" aria-hidden="true"></span>
                            <div class="flex items-center gap-2">
                                <p class="text-md font-medium text-gray-900">{{ $googleAccount->name }}</p>
                                <p class="mt-0.5 whitespace-nowrap rounded-md bg-{{ $googleAccount->status->color() }}-50 px-1.5 py-0.5 text-xs font-medium text-{{ $googleAccount->status->color() }}-700 ring-1 ring-inset ring-{{ $googleAccount->status->color() }}-600/20">{{ $googleAccount->status->label() }}</p>
                            </div>
                            <p class="truncate text-sm text-gray-500 flex items-center gap-2 mt-1">
                                <span>{{ $googleAccount->email }}</span>
                                <svg viewBox="0 0 2 2" class="h-0.5 w-0.5 flex-none fill-gray-500">
                                    <circle cx="1" cy="1" r="1" />
                                </svg>
                                <span>Created on {{ $googleAccount->created_at->toDateTimeString() }}</span>
                            </p>
                        </div>
                    </div>
                @endforeach
                @foreach($caldavAccounts as $caldavAccount)
                    <div class="relative flex items-center space-x-3 rounded-lg border border-gray-300 bg-white px-6 py-5 shadow-sm focus-within:ring-2 focus-within:ring-blue-500 focus-within:ring-offset-2 hover:border-gray-400">
                        <div class="flex-shrink-0">
                            <x-icons.calendar class="h-10 w-10" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <span class="absolute inset-0" aria-hidden="true"></span>
                            <div class="flex items-center gap-2">
                                <p class="text-md font-medium text-gray-900">{{ $caldavAccount->name }}</p>
                                <p class="mt-0.5 whitespace-nowrap rounded-md bg-green-50 px-1.5 py-0.5 text-xs font-medium text-green-700 ring-1 ring-inset ring-green-600/20">Connected</p>
                            </div>
                            <p class="truncate text-sm text-gray-500 flex items-center gap-2 mt-1">
                                <span>{{ $caldavAccount->email }}</span>
                                <svg viewBox="0 0 2 2" class="h-0.5 w-0.5 flex-none fill-gray-500">
                                    <circle cx="1" cy="1" r="1" />
                                </svg>
                                <span>Created on {{ $googleAccount->created_at->toDateTimeString() }}</span>
                            </p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
        <div class="mt-8">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">Connect a new account</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                @if(config('services.microsoft.enabled'))
                <a href="{{ route('outlook-accounts.auth') }}" 
                   class="flex items-center justify-center gap-3 rounded-lg border border-gray-200 bg-white p-4 shadow-sm hover:border-blue-500 hover:shadow-md transition-all duration-200">
                    <x-icons.microsoft class="h-6 w-6" />
                    <span class="font-medium text-gray-900">Outlook</span>
                </a>
                @endif

                @if(config('services.google.enabled'))
                <a href="{{ route('google-accounts.auth') }}"
                   class="flex items-center justify-center gap-3 rounded-lg border border-gray-200 bg-white p-4 shadow-sm hover:border-blue-500 hover:shadow-md transition-all duration-200">
                    <x-icons.google class="h-6 w-6" />
                    <span class="font-medium text-gray-900">Google</span>
                </a>
                @endif

                @if(config('services.caldav.enabled'))
                <a href="{{ route('caldav-accounts.create') }}"
                   class="flex items-center justify-center gap-3 rounded-lg border border-gray-200 bg-white p-4 shadow-sm hover:border-blue-500 hover:shadow-md transition-all duration-200">
                    <x-icons.calendar class="h-6 w-6 text-gray-600" />
                    <span class="font-medium text-gray-900">CalDAV</span>
                </a>
                @endif
            </div>
        </div>
    </div>

    <div>
        <div class="sm:flex sm:items-center">
            <div class="sm:flex-auto">
                <h1 class="text-lg font-semibold leading-6 text-gray-900">Displays</h1>
                <p class="mt-1 text-md text-gray-500">Your displays and their status.</p>
            </div>
            <div class="mt-4 sm:ml-16 sm:mt-0 sm:flex-none">
                @if(auth()->user()->can('create', \App\Models\Display::class))
                    <a href="{{ route('displays.create') }}" class="inline-flex items-center rounded-md bg-oxford px-3 py-2 text-center text-md font-semibold text-white">
                        <x-icons.plus class="h-5 w-5 mr-1" />
                        Create new display
                    </a>
                @endif
            </div>
        </div>
        <div class="mt-4 flow-root">
            <div class="-mx-4 -my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
                <div class="inline-block min-w-full py-2 align-middle sm:px-6 lg:px-8">
                    <div class="overflow-hidden shadow ring-1 ring-black ring-opacity-5 sm:rounded-lg">
                        <table class="min-w-full divide-y divide-gray-300">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900 sm:pl-6">Device name</th>
                                    <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Room name</th>
                                    <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Account</th>
                                    <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Calendar</th>
                                    <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Status</th>
                                    <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Devices</th>
                                    <th scope="col" class="relative py-3.5 pl-3 pr-4 sm:pr-6">
                                        <span class="sr-only">Actions</span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 bg-white">
                                @forelse($displays as $display)
                                    <tr>
                                        <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6">{{ $display->name }}</td>
                                        <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">{{ $display->display_name }}</td>
                                        <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                                            @if($display->calendar->outlookAccount)
                                                <div class="flex items-center">
                                                    <x-icons.microsoft class="mr-2 size-4 text-muted-foreground inline-flex" />
                                                    <span>{{ $display->calendar->outlookAccount->name }}</span>
                                                </div>
                                            @endif
                                            @if($display->calendar->googleAccount)
                                                <div class="flex items-center">
                                                    <x-icons.google class="mr-2 size-4 text-muted-foreground inline-flex" />
                                                    <span>{{ $display->calendar->googleAccount->name }}</span>
                                                </div>
                                            @endif
                                            @if($display->calendar->caldavAccount)
                                                <div class="flex items-center">
                                                    <x-icons.calendar class="mr-2 size-4 text-muted-foreground inline-flex" />
                                                    <span>{{ $display->calendar->caldavAccount->name }}</span>
                                                </div>
                                            @endif
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">{{ $display->calendar->name }}</td>
                                        <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                                            <span class="inline-flex items-center rounded-md bg-{{ $display->status->color() }}-50 px-2 py-1 text-xs font-medium text-{{ $display->status->color() }}-700 ring-1 ring-inset ring-{{ $display->status->color() }}-600/20">{{ $display->status->label() }}</span>
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                                            @forelse($display->devices as $device)
                                                {{ $device->name }}<br/>
                                            @empty
                                                -
                                            @endforelse
                                        </td>
                                        <td class="relative whitespace-nowrap py-4 pl-3 pr-4 text-right text-sm font-medium sm:pr-6">
                                            <div class="flex justify-end gap-3">
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
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="py-12 text-center">
                                            <div class="flex flex-col items-center justify-center">
                                                <x-icons.display class="h-12 w-12 text-gray-400" />
                                                <h3 class="mt-2 text-sm font-semibold text-gray-900">No displays</h3>
                                                <p class="mt-1 text-sm text-gray-500">Get started by creating a new display.</p>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
