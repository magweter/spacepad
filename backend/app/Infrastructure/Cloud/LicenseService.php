<?php

namespace App\Infrastructure\Cloud;

use Exception;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use LemonSqueezy\Laravel\Exceptions\LemonSqueezyApiError;
use LemonSqueezy\Laravel\Exceptions\LicenseKeyNotFound;
use LemonSqueezy\Laravel\Exceptions\MalformedDataError;

class LicenseService
{
    public const VERSION = '1.8.5';

    public const API = 'https://api.lemonsqueezy.com/v1';

    /**
     * Perform a Lemon Squeezy API call.
     *
     * @throws Exception
     * @throws LemonSqueezyApiError
     */
    public static function api(string $method, string $uri, array $payload = []): Response
    {
        if (empty($apiKey = config('lemon-squeezy.api_key'))) {
            throw new Exception('Lemon Squeezy API key not set.');
        }

        /** @var Response $response */
        $response = Http::withToken($apiKey)
            ->withUserAgent('LemonSqueezy\Laravel/' . static::VERSION)
            ->accept('application/vnd.api+json')
            ->contentType('application/vnd.api+json')
            ->$method(static::API . "/{$uri}", $payload);

        return $response;
    }

    /**
     * @throws LemonSqueezyApiError
     * @throws LicenseKeyNotFound|MalformedDataError
     */
    public static function getLicenseKey(string $id): array
    {
        $response = static::api('GET', "license-keys/$id");
        if ($response->notFound()) {
            throw new LicenseKeyNotFound();
        }

        if ($response->failed()) {
            throw new LemonSqueezyApiError($response['error'], (int) $response['error']);
        }

        return $response->json();
    }

    /**
     * @throws LemonSqueezyApiError
     * @throws LicenseKeyNotFound|MalformedDataError
     */
    public static function activateLicense(array $payload = []): array
    {
        $response = static::api('POST', 'licenses/activate', $payload);
        if ($response->notFound()) {
            throw new LicenseKeyNotFound();
        }

        if ($response->failed()) {
            throw new LemonSqueezyApiError($response['error'], (int) $response['error']);
        }

        return $response->json();
    }
}
