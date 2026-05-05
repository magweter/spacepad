@props(['display'])

@php
    $statusBadgeClass = match ($display->status) {
        \App\Enums\DisplayStatus::READY => 'bg-sky-50 text-sky-800 ring-sky-600/15',
        \App\Enums\DisplayStatus::ACTIVE => 'bg-emerald-50 text-emerald-800 ring-emerald-600/15',
        \App\Enums\DisplayStatus::DEACTIVATED => 'bg-gray-100 text-gray-700 ring-gray-600/10',
        \App\Enums\DisplayStatus::ERROR => 'bg-red-50 text-red-800 ring-red-600/15',
    };
@endphp

<tr class="transition-colors hover:bg-gray-50/90">
    <td class="whitespace-nowrap py-4 pl-4 pr-3 align-middle sm:pl-4">
        <div class="min-w-0 max-w-xs sm:max-w-sm">
            <div class="text-sm font-semibold leading-6 text-gray-900 truncate">{{ $display->name }}</div>
            @if(filled($display->display_name))
                <div class="text-xs leading-5 text-gray-500 truncate">{{ $display->display_name }}</div>
            @endif
        </div>
    </td>
    <td class="whitespace-nowrap px-3 py-4 align-middle text-sm">
        @if($display->calendar->outlookAccount)
            <div class="flex min-w-0 max-w-[11rem] sm:max-w-xs items-center gap-2">
                <x-icons.microsoft class="h-4 w-4 flex-shrink-0 text-gray-500" />
                <span class="truncate font-medium text-gray-900">{{ $display->calendar->outlookAccount->name }}</span>
            </div>
        @elseif($display->calendar->googleAccount)
            <div class="flex min-w-0 max-w-[11rem] sm:max-w-xs items-center gap-2">
                <x-icons.google class="h-4 w-4 flex-shrink-0 text-gray-500" />
                <span class="truncate font-medium text-gray-900">{{ $display->calendar->googleAccount->name }}</span>
            </div>
        @elseif($display->calendar->caldavAccount)
            <div class="flex min-w-0 max-w-[11rem] sm:max-w-xs items-center gap-2">
                <x-icons.calendar class="h-4 w-4 flex-shrink-0 text-gray-500" />
                <span class="truncate font-medium text-gray-900">{{ $display->calendar->caldavAccount->name }}</span>
            </div>
        @endif
    </td>
    <td class="whitespace-nowrap px-3 py-4 align-middle text-sm">
        <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset {{ $statusBadgeClass }}">
            {{ $display->status->label() }}
        </span>
    </td>
    <td class="px-3 py-4 align-middle text-sm text-gray-600">
        <div class="flex flex-col gap-1">
            @if($display->devices->isNotEmpty())
                <div class="group relative inline-flex w-fit">
                    <button type="button" class="text-left text-sm font-medium text-gray-900 hover:text-gray-700">
                        {{ $display->devices->count() }} device{{ $display->devices->count() > 1 ? 's' : '' }}
                        <span class="ml-1 inline-block align-middle text-gray-400">
                            <x-icons.information class="h-3.5 w-3.5" />
                        </span>
                    </button>
                    <div class="absolute bottom-full left-0 z-20 mb-2 hidden w-max max-w-xs rounded-lg bg-gray-900 px-3 py-2 text-xs text-white shadow-lg group-hover:block group-focus-within:block">
                        <div class="space-y-1.5">
                            @foreach($display->devices as $device)
                                <div class="flex flex-wrap items-baseline gap-x-1.5 gap-y-0">
                                    <span class="font-medium">{{ $device->name }}</span>
                                    @if($device->last_activity_at)
                                        <span class="text-gray-400">· {{ $device->last_activity_at->diffForHumans() }}</span>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                        <div class="absolute left-4 top-full h-0 w-0 border-x-4 border-t-4 border-transparent border-t-gray-900"></div>
                    </div>
                </div>
                @if($display->last_sync_at)
                    <span class="text-xs text-gray-500">Synced {{ $display->last_sync_at->diffForHumans() }}</span>
                @endif
            @else
                <span class="text-sm text-gray-500">No devices linked</span>
                @if($display->last_sync_at)
                    <span class="text-xs text-gray-500">Last calendar sync {{ $display->last_sync_at->diffForHumans() }}</span>
                @endif
            @endif
        </div>
    </td>
    <td class="relative whitespace-nowrap py-4 pl-3 pr-4 text-right align-middle text-sm font-medium sm:pr-4">
        <div class="flex justify-end gap-x-2">
            <form action="{{ route('displays.updateStatus', $display) }}" method="POST" class="inline">
                @csrf
                @method('PATCH')
                <input type="hidden" name="status" value="{{ $display->status === \App\Enums\DisplayStatus::ACTIVE ? 'deactivated' : 'active' }}">
                <button type="submit" class="inline-flex items-center rounded-md bg-white px-2.5 py-1.5 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50" title="{{ $display->status === \App\Enums\DisplayStatus::ACTIVE ? 'Pause display' : 'Resume display' }}">
                    @if($display->status === \App\Enums\DisplayStatus::ACTIVE)
                        <x-icons.pause class="h-4 w-4" />
                    @else
                        <x-icons.play class="h-4 w-4" />
                    @endif
                </button>
            </form>
            @if(auth()->user()->hasProForCurrentWorkspace())
                <a href="{{ route('displays.customization', $display) }}" class="inline-flex items-center rounded-md bg-white px-2.5 py-1.5 text-sm font-semibold text-indigo-600 shadow-sm ring-1 ring-inset ring-indigo-300 hover:bg-indigo-50" title="Customize display (Pro)">
                    <x-icons.brush class="h-4 w-4" />
                </a>
                <a href="{{ route('displays.settings.index', $display) }}" class="inline-flex items-center rounded-md bg-white px-2.5 py-1.5 text-sm font-semibold text-blue-600 shadow-sm ring-1 ring-inset ring-blue-300 hover:bg-blue-50" title="Display settings (Pro)">
                    <x-icons.settings class="h-4 w-4" />
                </a>
            @else
                <span class="inline-flex items-center rounded-md bg-gray-100 px-2.5 py-1.5 text-sm font-semibold text-gray-400 shadow-sm ring-1 ring-inset ring-gray-200 cursor-not-allowed" title="Upgrade to Pro to unlock customization">
                    <x-icons.brush class="h-4 w-4" />
                </span>
                <span class="inline-flex items-center rounded-md bg-gray-100 px-2.5 py-1.5 text-sm font-semibold text-gray-400 shadow-sm ring-1 ring-inset ring-gray-200 cursor-not-allowed" title="Upgrade to Pro to unlock settings">
                    <x-icons.settings class="h-4 w-4" />
                </span>
            @endif
            <form action="{{ route('displays.delete', $display) }}" method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this display?');">
                @csrf
                @method('DELETE')
                <button type="submit" class="inline-flex items-center rounded-md bg-white px-2.5 py-1.5 text-sm font-semibold text-red-600 shadow-sm ring-1 ring-inset ring-red-300 hover:bg-red-50" title="Delete display">
                    <x-icons.trash class="h-4 w-4" />
                </button>
            </form>
        </div>
    </td>
</tr>
