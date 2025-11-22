@extends('layouts.base')
@section('title', 'Panels')
@section('container_class', 'max-w-7xl')

@section('content')
    <x-alerts.alert />

    <x-cards.card>
        <div class="sm:flex sm:items-center mb-4">
            <div class="sm:flex-auto">
                <h2 class="text-lg font-semibold leading-6 text-gray-900">Panels</h2>
                <p class="mt-1 text-sm text-gray-500">Web-based displays showing multiple meeting rooms. Perfect for PC browsers and wayfinding.</p>
            </div>
            <div class="mt-4 sm:ml-16 sm:mt-0 sm:flex-none">
                <a href="{{ route('panels.create') }}" class="inline-flex items-center rounded-md bg-oxford px-3 py-2 text-center text-sm font-semibold text-white">
                    <x-icons.plus class="h-5 w-5 mr-1" />
                    Create new panel
                </a>
            </div>
        </div>

        @if($panels->isEmpty())
            <div class="text-center py-12">
                <x-icons.display class="mx-auto h-12 w-12 text-gray-400" />
                <h3 class="mt-2 text-sm font-semibold text-gray-900">No panels</h3>
                <p class="mt-1 text-sm text-gray-500">Get started by creating a new panel.</p>
                <div class="mt-6">
                    <a href="{{ route('panels.create') }}" class="inline-flex items-center rounded-md bg-oxford px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-oxford-600">
                        <x-icons.plus class="h-5 w-5 mr-1" />
                        Create panel
                    </a>
                </div>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-300">
                    <thead>
                        <tr>
                            <th scope="col" class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900 sm:pl-0">Name</th>
                            <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Displays</th>
                            <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Display Mode</th>
                            <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Created</th>
                            <th scope="col" class="relative py-3.5 pl-3 pr-4 sm:pr-0">
                                <span class="sr-only">Actions</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach($panels as $panel)
                            <tr>
                                <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-0">
                                    {{ $panel->name }}
                                </td>
                                <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                                    {{ $panel->displays_count }} {{ Str::plural('display', $panel->displays_count) }}
                                </td>
                                <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                                    {{ $panel->display_mode->label() }}
                                </td>
                                <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                                    {{ $panel->created_at->format('M d, Y') }}
                                </td>
                                <td class="relative whitespace-nowrap py-4 pl-3 pr-4 text-right text-sm font-medium sm:pr-0">
                                    <div class="flex items-center justify-end gap-2">
                                        <a href="{{ route('panels.show', $panel) }}" target="_blank" class="text-oxford hover:text-oxford-600" title="View panel">
                                            <x-icons.eye class="h-5 w-5" />
                                        </a>
                                        <a href="{{ route('panels.edit', $panel) }}" class="text-oxford hover:text-oxford-600" title="Edit panel">
                                            <x-icons.pencil class="h-5 w-5" />
                                        </a>
                                        <form action="{{ route('panels.destroy', $panel) }}" method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this panel?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-red-600 hover:text-red-900" title="Delete panel">
                                                <x-icons.trash class="h-5 w-5" />
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-cards.card>
@endsection

