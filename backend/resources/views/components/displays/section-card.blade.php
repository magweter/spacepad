@props([
    'display',
    // Key from DisplaySettingSections (behavior, display, texts, branding, advertisement)
    'section',
    'label',
    'description' => null,
    // Set for sections that upload files
    'multipart' => false,
])

@php
    $hasProfile = (bool) $display->display_profile_id;
    $follows = \App\Helpers\DisplaySettings::sectionFollowsProfile($display, $section);
@endphp

<div class="border border-gray-200 rounded-lg p-6">
    <div class="flex items-start justify-between gap-4 mb-4">
        <div>
            <h3 class="text-base font-semibold text-gray-900">{{ $label }}</h3>
            @if($description)
                <p class="mt-1 text-sm text-gray-500">{{ $description }}</p>
            @endif
        </div>

        @if($hasProfile)
            {{-- This block sits outside the section form on purpose: the reset is its own form. --}}
            <div class="shrink-0 flex items-center gap-3">
                @if($follows)
                    <span class="inline-flex items-center gap-1 rounded-md bg-green-50 px-2 py-1 text-xs font-medium text-green-700 ring-1 ring-inset ring-green-600/20">
                        Follows profile
                    </span>
                @else
                    <span class="inline-flex items-center gap-1 rounded-md bg-amber-50 px-2 py-1 text-xs font-medium text-amber-700 ring-1 ring-inset ring-amber-600/20">
                        Own settings
                    </span>
                    <form action="{{ route('displays.section.reset', ['display' => $display, 'section' => $section]) }}" method="POST">
                        @csrf
                        <button type="submit"
                            class="whitespace-nowrap rounded-md bg-white px-2.5 py-1.5 text-xs font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                            Follow profile
                        </button>
                    </form>
                @endif
            </div>
        @endif
    </div>

    <form action="{{ route('displays.section.update', ['display' => $display, 'section' => $section]) }}"
          method="POST"
          @if($multipart) enctype="multipart/form-data" @endif>
        @csrf
        @method('PUT')

        {{ $slot }}

        <div class="mt-6 flex items-center justify-end gap-4 border-t border-gray-100 pt-4">
            @if($hasProfile && $follows)
                <p class="text-xs text-gray-500">
                    Saving detaches this section from <span class="font-medium">{{ $display->profile->name }}</span>;
                    the other sections keep following it.
                </p>
            @endif
            <button type="submit" class="shrink-0 rounded-md bg-oxford px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-oxford-600">
                Save
            </button>
        </div>
    </form>
</div>
