@props([
    // Short explanation of what happens when this option is enabled or set.
    'text',
    // Flip the bubble to the right edge when the tip sits near the right of the container.
    'align' => 'left',
])

{{-- Hover/focus explanation next to a setting. Follows the same group-hover pattern as the
     tooltips already used on the dashboard, but reachable by keyboard too. --}}
<span class="relative inline-flex group align-middle">
    <button type="button" tabindex="0"
            class="text-gray-400 hover:text-gray-600 focus:text-gray-600 focus:outline-none"
            aria-label="More information">
        <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
        </svg>
    </button>
    <span role="tooltip"
          class="pointer-events-none absolute bottom-full z-20 mb-2 w-64 rounded-lg bg-gray-900 p-3 text-xs font-normal leading-relaxed text-white opacity-0 shadow-lg transition-opacity duration-150 invisible group-hover:visible group-hover:opacity-100 group-focus-within:visible group-focus-within:opacity-100 {{ $align === 'right' ? 'right-0' : 'left-0' }}">
        {{ $text }}
    </span>
</span>
