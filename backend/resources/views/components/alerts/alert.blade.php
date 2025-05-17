@if (session('success') || session('error'))
    @if(session('success'))
        <div id="alert" class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div id="alert" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4">
            {{ session('error') }}
        </div>
    @endif

    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const alert = document.getElementById('alert');
            if (alert) {
                const closeButton = alert.querySelector('svg');
                closeButton.addEventListener('click', function() {
                    alert.remove();
                });

                // Auto-hide after 5 seconds
                setTimeout(() => {
                    alert.remove();
                }, 5000);
            }
        });
    </script>
    @endpush
@endif 