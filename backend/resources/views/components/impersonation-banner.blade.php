@if(session('impersonating'))
    <div class="bg-yellow-50 border-b border-yellow-200">
        <div class="mx-auto container px-4 sm:px-6">
            <div class="flex h-12 items-center justify-between px-4 sm:px-0">
                <div class="flex items-center gap-2">
                    <span class="text-sm font-medium text-yellow-800">
                        ⚠️ You are impersonating {{ auth()->user()->email }}
                    </span>
                </div>
                <form action="{{ route('admin.stop-impersonating') }}" method="POST" class="inline">
                    @csrf
                    <button type="submit" class="rounded-md bg-yellow-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-yellow-700">
                        Stop Impersonating
                    </button>
                </form>
            </div>
        </div>
    </div>
@endif

