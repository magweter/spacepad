@props([
    'type' => 'info',
    'title' => null,
    'message' => null,
    'dismissible' => false,
    'autoDismiss' => true,
    'autoDismissDelay' => 5000,
    'errors' => null,
])

@php
    // Set type and title based on session messages
    if (session('success')) {
        $type = 'success';
        $title = 'Success';
    } elseif (session('error')) {
        $type = 'error';
        $title = 'Something went wrong';
    } elseif (session('warning')) {
        $type = 'warning';
        $title = 'Heads up';
    } elseif (session('info')) {
        $type = 'info';
        $title = 'Please note';
    }

    $hasErrors = $errors->any() && !$errors->has('license_key');
    if ($hasErrors) {
        $type = 'error';
        $title = 'There were errors with your submission';
    }

    $alertClasses = [
        'success' => 'bg-green-50 ring-green-600',
        'error' => 'bg-red-50 ring-red-600',
        'warning' => 'bg-yellow-50 ring-yellow-600',
        'info' => 'bg-blue-50 ring-blue-600',
    ][$type] ?? 'bg-blue-50 ring-blue-600';

    $titleClasses = [
        'success' => 'text-green-700',
        'error' => 'text-red-700',
        'warning' => 'text-yellow-700',
        'info' => 'text-blue-700',
    ][$type] ?? 'text-blue-700';

    $messageClasses = [
        'success' => 'text-green-700',
        'error' => 'text-red-700',
        'warning' => 'text-yellow-700',
        'info' => 'text-blue-700',
    ][$type] ?? 'text-blue-700';
@endphp

@if(session('success') || session('error') || session('warning') || session('info') || $hasErrors)
    <div id="alert" class="rounded-md p-4 mb-4 ring-1 ring-inset {{ $alertClasses }}">
        <div class="flex flex-col">
            @if($title)
                <h3 class="text-base font-semibold mb-1 {{ $titleClasses }}">{{ $title }}</h3>
            @endif
            <div class="text-sm {{ $messageClasses }}">
                @if($message)
                    <p>{{ $message }}</p>
                @endif
                @if($hasErrors)
                    <ul class="list-disc pl-5 space-y-1">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                @endif
                @if(session('success'))
                    <p>{{ session('success') }}</p>
                @endif
                @if(session('error'))
                    <p>{{ session('error') }}</p>
                @endif
                @if(session('warning'))
                    <p>{{ session('warning') }}</p>
                @endif
                @if(session('info'))
                    <p>{{ session('info') }}</p>
                @endif
            </div>
        </div>
    </div>

    @if($autoDismiss)
        @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const alert = document.getElementById('alert');
                if (alert) {
                    setTimeout(() => {
                        alert.remove();
                    }, {{ $autoDismissDelay }});
                }
            });
        </script>
        @endpush
    @endif
@endif
