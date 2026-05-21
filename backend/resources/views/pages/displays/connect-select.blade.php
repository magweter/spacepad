@extends('layouts.display')
@section('title', 'Select Display — ' . config('app.name'))

@section('content')
<div class="min-h-screen bg-gray-950 flex items-center justify-center p-6">
    <div class="w-full max-w-lg">

        <div class="text-center mb-8">
            <img src="/images/logo-white.svg" alt="{{ config('app.name') }}" class="h-10 w-auto mx-auto mb-6 opacity-80">
            <h1 class="text-2xl font-bold text-white">Select a display</h1>
            <p class="text-gray-400 mt-2 text-sm">{{ $workspaceName }}</p>
        </div>

        @if($displays->isEmpty())
            <div class="text-center text-gray-500 py-12">
                <p>No displays found for this workspace.</p>
                <a href="{{ route('displays.connect') }}" class="inline-block mt-4 text-emerald-400 hover:text-emerald-300 text-sm">
                    ← Try again
                </a>
            </div>
        @else
            <div class="grid gap-3">
                @foreach($displays as $display)
                    <a href="{{ route('displays.public', $display->display_token) }}"
                       class="group flex items-center gap-4 rounded-xl bg-gray-800 hover:bg-gray-700 border border-gray-700 hover:border-emerald-500 px-5 py-4 transition-all">
                        <div class="flex-shrink-0 h-10 w-10 rounded-full bg-gray-700 group-hover:bg-emerald-900 flex items-center justify-center transition-colors">
                            <svg class="h-5 w-5 text-gray-400 group-hover:text-emerald-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25v1.007a3 3 0 01-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0115 18.257V17.25m6-12V15a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 15V5.25m18 0A2.25 2.25 0 0018.75 3H5.25A2.25 2.25 0 003 5.25m18 0H3" />
                            </svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="text-white font-semibold truncate">{{ $display->display_name }}</div>
                            <div class="text-gray-400 text-sm truncate">{{ $display->name }}</div>
                        </div>
                        <div class="flex-shrink-0 text-gray-600 group-hover:text-emerald-400 transition-colors">
                            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                            </svg>
                        </div>
                    </a>
                @endforeach
            </div>

            <div class="text-center mt-6">
                <a href="{{ route('displays.connect') }}" class="text-gray-600 hover:text-gray-400 text-sm transition-colors">
                    ← Use a different code
                </a>
            </div>
        @endif

    </div>
</div>
@endsection
