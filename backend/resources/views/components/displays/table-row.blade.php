@props(['display'])

<tr>
    <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-0">
        <div class="font-medium text-gray-900">{{ $display->name }}</div>
        <div class="text-gray-500">{{ $display->display_name }}</div>
    </td>
    <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
        <div class="flex flex-col gap-1">
            @if($display->calendar->outlookAccount)
                <div class="flex items-center">
                    <x-icons.microsoft class="h-4 w-4 text-gray-900 mr-2" />
                    <span class="text-gray-900">{{ $display->calendar->outlookAccount->name }}</span>
                </div>
            @endif
            @if($display->calendar->googleAccount)
                <div class="flex items-center">
                    <x-icons.google class="h-4 w-4 text-gray-900 mr-2" />
                    <span class="text-gray-900">{{ $display->calendar->googleAccount->name }}</span>
                </div>
            @endif
            @if($display->calendar->caldavAccount)
                <div class="flex items-center">
                    <x-icons.calendar class="h-4 w-4 text-gray-900 mr-2" />
                    <span class="text-gray-900">{{ $display->calendar->caldavAccount->name }}</span>
                </div>
            @endif
            <div class="text-gray-500">{{ $display->calendar->name }}</div>
        </div>
    </td>
    <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
        <span class="inline-flex items-center rounded-md bg-{{ $display->status->color() }}-50 px-2 py-1 text-xs font-medium text-{{ $display->status->color() }}-700 ring-1 ring-inset ring-{{ $display->status->color() }}-600">
            {{ $display->status->label() }}
        </span>
    </td>
    <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
        <div class="flex flex-col gap-1">
            <div class="flex items-center gap-x-1.5">
                @if($display->devices->isNotEmpty())
                    <div class="flex-none rounded-full bg-emerald-500/20 p-1">
                        <div class="h-2 w-2 rounded-full bg-emerald-500"></div>
                    </div>
                    <div class="group relative">
                        <button type="button" class="flex items-center gap-x-1 text-sm text-gray-500 hover:text-gray-900">
                            <span>{{ $display->devices->count() }} device{{ $display->devices->count() > 1 ? 's' : '' }}</span>
                            <x-icons.information class="h-4 w-4 text-gray-400" />
                        </button>
                        <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 hidden group-hover:block">
                            <div class="rounded-md bg-gray-900 px-2 py-1 text-xs text-white shadow-lg">
                                <div class="whitespace-nowrap">
                                    @foreach($display->devices as $device)
                                        <div class="flex items-center gap-x-1">
                                            <span>{{ $device->name }}</span>
                                            @if($device->last_activity_at)
                                                <span class="text-gray-400">({{ $device->last_activity_at->diffForHumans() }})</span>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                @else
                    <div class="flex-none rounded-full bg-gray-500/20 p-1">
                        <div class="h-2 w-2 rounded-full bg-gray-500"></div>
                    </div>
                    <span class="text-gray-500">No devices</span>
                @endif
            </div>
            @if($display->last_sync_at)
                <div class="text-gray-400 text-xs">Last sync {{ $display->last_sync_at->diffForHumans() }}</div>
            @endif
        </div>
    </td>
    <td class="relative whitespace-nowrap py-4 pl-3 pr-4 text-right text-sm font-medium sm:pr-0">
        <div class="flex justify-end gap-x-2">
            <form action="{{ route('displays.updateStatus', $display) }}" method="POST" class="inline">
                @csrf
                @method('PATCH')
                <input type="hidden" name="status" value="{{ $display->status === \App\Enums\DisplayStatus::ACTIVE ? 'deactivated' : 'active' }}">
                <button type="submit" class="inline-flex items-center rounded-md bg-white px-2.5 py-1.5 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
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

