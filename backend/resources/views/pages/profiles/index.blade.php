@extends('layouts.base')
@section('title', 'Profiles')

@section('content')
    <x-cards.card>
        <div class="sm:flex sm:items-center mb-4">
            <div class="sm:flex-auto">
                <h2 class="text-lg font-semibold leading-6 text-gray-900">Profiles</h2>
                <p class="mt-1 text-sm text-gray-500">
                    Reusable sets of display settings. Link displays to a profile and they inherit its settings automatically.
                </p>
            </div>
            <div class="mt-4 sm:ml-16 sm:mt-0 sm:flex-none">
                <a href="{{ route('profiles.create') }}" class="inline-flex items-center rounded-md bg-oxford px-3 py-2 text-center text-sm font-semibold text-white">
                    <x-icons.plus class="h-5 w-5 mr-1" />
                    Create new profile
                </a>
            </div>
        </div>

        {{-- Session Status Alert --}}
        <x-alerts.alert />

        <div class="mt-6 flow-root">
            <div class="-mx-4 -my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
                <div class="inline-block min-w-full py-2 align-middle sm:px-6 lg:px-8">
                    <table class="min-w-full divide-y divide-gray-300">
                        <thead>
                        <tr>
                            <th scope="col" class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900 sm:pl-0">Name</th>
                            <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Linked displays</th>
                            <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Created by</th>
                            <th scope="col" class="relative py-3.5 pr-4 pl-3 sm:pr-0">
                                <span class="sr-only">Actions</span>
                            </th>
                        </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                        @forelse($profiles as $profile)
                            <tr>
                                <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-0">
                                    @can('update', $profile)
                                        <a href="{{ route('profiles.edit', $profile) }}" class="text-blue-600 hover:text-blue-900">
                                            {{ $profile->name }}
                                        </a>
                                    @else
                                        {{ $profile->name }}
                                    @endcan
                                </td>
                                <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                                    {{ $profile->displays_count }} {{ $profile->displays_count === 1 ? 'display' : 'displays' }}
                                </td>
                                <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                                    {{ $profile->user?->name ?? '—' }}
                                </td>
                                <td class="relative whitespace-nowrap py-4 pl-3 pr-4 text-right text-sm font-medium sm:pr-0">
                                    <div class="flex items-center justify-end gap-2">
                                        @can('update', $profile)
                                            <a href="{{ route('profiles.edit', $profile) }}" class="text-gray-600 hover:text-gray-900" title="Edit">
                                                <x-icons.settings class="h-5 w-5" />
                                            </a>
                                        @endcan
                                        @can('delete', $profile)
                                            <form action="{{ route('profiles.destroy', $profile) }}" method="POST" class="inline" onsubmit="return confirm('Delete this profile? Linked displays will keep their current settings but stop inheriting from it.');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-red-600 hover:text-red-900" title="Delete">
                                                    <x-icons.trash class="h-5 w-5" />
                                                </button>
                                            </form>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="py-16 text-center">
                                    <div class="flex flex-col items-center justify-center">
                                        <x-icons.display class="h-12 w-12 text-orange mb-3" />
                                        <h3 class="mb-2 text-md font-semibold text-gray-900">
                                            No profiles yet
                                        </h3>
                                        <p class="mb-6 text-sm text-gray-500 max-w-sm">Create a profile to manage settings for many displays at once.</p>
                                        <a href="{{ route('profiles.create') }}" class="inline-flex items-center rounded-md bg-oxford px-3 py-2 text-center text-sm font-semibold text-white">
                                            <x-icons.plus class="h-5 w-5 mr-1" />
                                            Create new profile
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </x-cards.card>
@endsection
