<?php

/**
 * Disable OpenTelemetry Laravel instrumentation if extension is not loaded.
 * This prevents warnings in dev/CI environments where the extension isn't installed.
 * 
 * This file should be included before the Composer autoloader is loaded.
 */
if (!extension_loaded('opentelemetry') && !isset($_ENV['OTEL_PHP_DISABLED_INSTRUMENTATIONS'])) {
    $_ENV['OTEL_PHP_DISABLED_INSTRUMENTATIONS'] = 'laravel';
    putenv('OTEL_PHP_DISABLED_INSTRUMENTATIONS=laravel');
}

