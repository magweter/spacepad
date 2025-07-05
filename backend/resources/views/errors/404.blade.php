@extends('layouts.error')

@section('title', 'Page Not Found')

@section('content')
    <div class="mt-6 text-center">
        <h1 class="text-6xl font-bold text-oxford">404</h1>
        <h2 class="mt-4 text-2xl font-semibold text-gray-900">Page Not Found</h2>
        <p class="mt-2 text-gray-600">The page you're looking for doesn't exist or has been moved.</p>
        <div class="mt-6">
            <a href="{{ route('dashboard') }}" class="inline-flex items-center rounded-md bg-oxford px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600">
                Go back to dashboard
            </a>
        </div>
    </div>
@endsection 