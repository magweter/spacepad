@extends('layouts.display')
@section('title', 'Connect Display — ' . config('app.name'))

@section('content')
<div class="min-h-screen bg-gray-950 flex items-center justify-center p-6">
    <div class="w-full max-w-md">

        <div class="text-center mb-10">
            <img src="/images/logo-white.svg" alt="{{ config('app.name') }}" class="h-10 w-auto mx-auto mb-6 opacity-80">
            <h1 class="text-2xl font-bold text-white">Connect this display</h1>
            <p class="text-gray-400 mt-2 text-sm">
                Enter the connect code shown on your management dashboard.
            </p>
        </div>

        <form action="{{ route('displays.connect.lookup') }}" method="POST">
            @csrf

            <div class="mb-4">
                <input
                    type="text"
                    name="code"
                    id="code"
                    inputmode="numeric"
                    maxlength="7"
                    placeholder="123 456"
                    autofocus
                    value="{{ old('code') }}"
                    class="w-full rounded-xl bg-gray-800 border border-gray-700 text-white text-center text-3xl font-mono tracking-widest px-6 py-4 focus:outline-none focus:ring-2 focus:ring-emerald-500 placeholder-gray-600"
                >
                @error('code')
                    <p class="mt-2 text-sm text-red-400 text-center">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit"
                class="w-full rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-semibold py-3 text-base transition-colors mt-2">
                Continue
            </button>
        </form>

        <p class="text-center text-gray-600 text-xs mt-8">
            Find the connect code under <strong class="text-gray-500">Displays</strong> on your management dashboard.
        </p>
    </div>
</div>
@endsection
