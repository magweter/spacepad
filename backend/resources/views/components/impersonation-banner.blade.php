{{-- Across the full width of the page, above the portal shell and the Blade layouts alike. --}}
@if(session('impersonating'))
    <div class="flex min-h-12 items-center justify-between gap-4 border-b border-yellow-200 bg-yellow-50 px-6 py-2 md:px-8">
        <span class="inline-flex items-center gap-2 text-sm font-medium text-yellow-800">
            <svg class="h-4 w-4 flex-none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><path d="M12 9v4M12 17h.01"/></svg>
            You are impersonating {{ auth()->user()->email }}
        </span>
        <form action="{{ route('admin.stop-impersonating') }}" method="POST" class="flex-none">
            @csrf
            <button type="submit" class="rounded-md bg-yellow-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-yellow-700">
                Stop impersonating
            </button>
        </form>
    </div>
@endif
