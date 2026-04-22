@extends('layouts.base')
@section('title', isset($item) ? 'Edit roadmap item' : 'New roadmap item')

@section('content')
    <div class="max-w-2xl">
        <form
            action="{{ isset($item) ? route('admin.roadmap.update', $item) : route('admin.roadmap.store') }}"
            method="POST"
            class="space-y-6"
        >
            @csrf
            @if(isset($item)) @method('PUT') @endif

            <x-cards.card>
                <div class="space-y-5">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Title <span class="text-red-500">*</span></label>
                        <input
                            type="text"
                            name="title"
                            value="{{ old('title', $item->title ?? '') }}"
                            required
                            maxlength="150"
                            placeholder="e.g. CalDAV two-way sync"
                            class="block w-full rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-900 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                        />
                        @error('title') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                        <textarea
                            name="description"
                            rows="3"
                            maxlength="2000"
                            placeholder="Optional — more detail shown in the panel"
                            class="block w-full rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-900 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 resize-none"
                        >{{ old('description', $item->description ?? '') }}</textarea>
                        @error('description') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Status <span class="text-red-500">*</span></label>
                            <select
                                name="status"
                                class="block w-full rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-900 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                            >
                                @foreach($statuses as $status)
                                    <option value="{{ $status->value }}" {{ old('status', $item->status->value ?? 'considering') === $status->value ? 'selected' : '' }}>
                                        {{ $status->label() }}
                                    </option>
                                @endforeach
                            </select>
                            @error('status') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Expected date</label>
                            <input
                                type="date"
                                name="expected_at"
                                value="{{ old('expected_at', isset($item) ? $item->expected_at?->format('Y-m-d') : '') }}"
                                class="block w-full rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-900 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                            />
                            @error('expected_at') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="w-32">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Sort order</label>
                        <input
                            type="number"
                            name="sort_order"
                            value="{{ old('sort_order', $item->sort_order ?? 0) }}"
                            min="0"
                            class="block w-full rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-900 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                        />
                        <p class="mt-1 text-xs text-gray-400">Lower = higher in list</p>
                    </div>
                </div>
            </x-cards.card>

            <div class="flex items-center gap-3">
                <button type="submit" class="inline-flex items-center rounded-md bg-oxford px-4 py-2 text-sm font-semibold text-white hover:bg-oxford-600">
                    {{ isset($item) ? 'Save changes' : 'Create item' }}
                </button>
                <a href="{{ route('admin.roadmap.index') }}" class="text-sm text-gray-500 hover:text-gray-700">Cancel</a>
            </div>
        </form>
    </div>
@endsection
