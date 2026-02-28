<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Grafana Faro Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Grafana Faro Real User Monitoring (RUM).
    | Faro collects frontend telemetry data (errors, performance, user interactions)
    | and sends it to Grafana Alloy for processing.
    |
    */

    'enabled' => env('FARO_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Faro Collector Endpoint
    |--------------------------------------------------------------------------
    |
    | The URL where Grafana Alloy FARO receiver is listening.
    | Default: http://localhost:12347/collect
    |
    | In Docker environments, use host.docker.internal to reach the host.
    | In production, use your actual Grafana Alloy endpoint.
    |
    */

    'collector_url' => env('FARO_COLLECTOR_URL', 'http://localhost:12347/collect'),

    /*
    |--------------------------------------------------------------------------
    | API Key
    |--------------------------------------------------------------------------
    |
    | The API key that must match the api_key configured in Grafana Alloy.
    | Default: faro-secret-key (change this in production!)
    |
    */

    'api_key' => env('FARO_API_KEY', 'faro-secret-key'),

    /*
    |--------------------------------------------------------------------------
    | Application Information
    |--------------------------------------------------------------------------
    |
    | Application metadata sent with Faro telemetry.
    |
    */

    'app' => [
        'name' => env('FARO_APP_NAME', env('APP_NAME', 'spacepad')),
        'version' => env('FARO_APP_VERSION', env('APP_VERSION', '1.0.0')),
        'environment' => env('FARO_APP_ENV', env('APP_ENV', 'local')),
    ],

    /*
    |--------------------------------------------------------------------------
    | Session Tracking
    |--------------------------------------------------------------------------
    |
    | Enable session tracking and user identification.
    |
    */

    'session_tracking' => env('FARO_SESSION_TRACKING', true),

    /*
    |--------------------------------------------------------------------------
    | Performance Monitoring
    |--------------------------------------------------------------------------
    |
    | Enable Web Vitals and performance metrics collection.
    |
    */

    'performance' => [
        'enabled' => env('FARO_PERFORMANCE_ENABLED', true),
        'observe_long_tasks' => env('FARO_OBSERVE_LONG_TASKS', true),
        'observe_resources' => env('FARO_OBSERVE_RESOURCES', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Error Tracking
    |--------------------------------------------------------------------------
    |
    | Enable automatic error and exception tracking.
    |
    */

    'errors' => [
        'enabled' => env('FARO_ERRORS_ENABLED', true),
        'capture_unhandled_rejections' => env('FARO_CAPTURE_UNHANDLED_REJECTIONS', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Console Logs
    |--------------------------------------------------------------------------
    |
    | Enable capturing console logs (errors and warnings).
    |
    */

    'console' => [
        'enabled' => env('FARO_CONSOLE_ENABLED', true),
        'levels' => env('FARO_CONSOLE_LEVELS', 'error,warn'), // Comma-separated: error, warn, info, debug
    ],

    /*
    |--------------------------------------------------------------------------
    | User Interactions
    |--------------------------------------------------------------------------
    |
    | Enable tracking user interactions (clicks, form submissions).
    |
    */

    'interactions' => [
        'enabled' => env('FARO_INTERACTIONS_ENABLED', true),
    ],

];

