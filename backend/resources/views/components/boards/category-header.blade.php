@props([
    // Category name, or the translated fallback label for the ungrouped group.
    'label',
    // Whether this is the first group on the board (no top spacing).
    'first' => false,
])

<div {{ $attributes->class(['flex items-center gap-4', 'mt-8' => ! $first]) }}>
    <span class="text-sm font-semibold uppercase tracking-widest board-text-secondary">{{ $label }}</span>
    <div class="flex-1 h-px board-border" style="border-top: 1px solid;"></div>
</div>
