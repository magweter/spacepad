@if(config('faro.enabled'))
<script type="module">
    import { initializeFaro, getWebInstrumentations } from 'https://cdn.jsdelivr.net/npm/@grafana/faro-web-sdk@latest/+esm';
    
    try {
        const faroInstance = initializeFaro({
            url: @json(config('faro.collector_url')),
            apiKey: @json(config('faro.api_key')),
            app: @json(config('faro.app')),
            instrumentations: getWebInstrumentations(),
            sessionTracking: {
                enabled: @json(config('faro.session_tracking')),
            },
        });
        
        // Store in window for debugging/access
        if (window) {
            Object.defineProperty(window, 'faroInstance', {
                value: faroInstance,
                writable: false,
                configurable: true
            });
        }
        
        console.log('[FARO] Initialized - RUM telemetry enabled');
    } catch (error) {
        console.error('[FARO] Failed to initialize:', error);
    }
</script>
@endif

