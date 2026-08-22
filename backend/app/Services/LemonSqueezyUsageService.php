<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Pushing billable usage to Lemon Squeezy.
 *
 * Extracted from UpdateLemonSqueezySubscriptions, where the quantity and usage-record paths
 * were near-identical 90-line methods that each fetched the same subscription separately.
 * The subscription-item id is stable, so it is cached.
 */
class LemonSqueezyUsageService
{
    private const BASE = 'https://api.lemonsqueezy.com/v1';

    /**
     * Resolve the subscription item to bill against.
     *
     * The shape of this response has clearly varied, hence the several fallbacks; they are
     * carried over verbatim rather than tidied on a guess.
     */
    public function resolveSubscriptionItemId(string $subscriptionId): ?string
    {
        return cache()->remember(
            "lemonsqueezy:subscription:{$subscriptionId}:item-id",
            now()->addDay(),
            fn () => $this->fetchSubscriptionItemId($subscriptionId)
        );
    }

    /**
     * Quantity-based billing.
     */
    public function setQuantity(string $subscriptionItemId, int $quantity): bool
    {
        return $this->quantityResponse($subscriptionItemId, $quantity)->successful();
    }

    /**
     * Metered billing.
     */
    public function recordUsage(string $subscriptionItemId, int $quantity): bool
    {
        return $this->usageResponse($subscriptionItemId, $quantity)->successful();
    }

    private function quantityResponse(string $subscriptionItemId, int $quantity): Response
    {
        return $this->request()->patch(self::BASE."/subscription-items/{$subscriptionItemId}", [
            'data' => [
                'type' => 'subscription-items',
                'id' => $subscriptionItemId,
                'attributes' => ['quantity' => $quantity],
            ],
        ]);
    }

    private function usageResponse(string $subscriptionItemId, int $quantity): Response
    {
        return $this->request()->post(self::BASE.'/usage-records', [
            'data' => [
                'type' => 'usage-records',
                'attributes' => [
                    'quantity' => $quantity,
                    'action' => 'set',
                ],
                'relationships' => [
                    'subscription-item' => [
                        'data' => [
                            'type' => 'subscription-items',
                            'id' => $subscriptionItemId,
                        ],
                    ],
                ],
            ],
        ]);
    }

    /**
     * Tell Lemon Squeezy what a subscription should now be billed for.
     *
     * The Pro variant is either quantity-based or metered, so one of these two calls is
     * always a no-op. Both are attempted and the outcome logged; once it is clear from
     * production which one answers, the dead branch can go.
     *
     * @param  array<string, mixed>  $context  extra detail for the log line
     */
    public function pushUnits(string $subscriptionId, int $units, array $context = []): bool
    {
        if (! $this->hasApiKey()) {
            Log::debug('No Lemon Squeezy API key configured; usage not pushed', $context + ['units' => $units]);

            return false;
        }

        $itemId = $this->resolveSubscriptionItemId($subscriptionId);

        if (! $itemId) {
            Log::warning('No Lemon Squeezy subscription item to bill against', $context + [
                'subscription_id' => $subscriptionId,
            ]);

            return false;
        }

        $quantity = $this->quantityResponse($itemId, $units);
        $usage = $this->usageResponse($itemId, $units);

        $quantityOk = $quantity->successful();
        $usageOk = $usage->successful();

        if (! $quantityOk && ! $usageOk) {
            // Both statuses and both reasons, because either one alone is ambiguous: a
            // metered price rejects the quantity update by design, so the interesting
            // question is always why the *other* call also failed.
            Log::warning('Neither billing method accepted the usage push', $context + [
                'subscription_item_id' => $itemId,
                'units' => $units,
                'quantity_status' => $quantity->status(),
                'quantity_error' => $this->errorDetail($quantity),
                'usage_status' => $usage->status(),
                'usage_error' => $this->errorDetail($usage),
            ]);

            return false;
        }

        Log::info('Pushed workspace usage to Lemon Squeezy', $context + [
            'subscription_item_id' => $itemId,
            'units' => $units,
            'quantity_accepted' => $quantityOk,
            'usage_record_accepted' => $usageOk,
        ]);

        return true;
    }

    public function hasApiKey(): bool
    {
        return (bool) config('lemon-squeezy.api_key');
    }

    /**
     * Why Lemon Squeezy turned a call down, in one line.
     *
     * The JSON:API detail when there is one, otherwise the raw body, capped so an HTML
     * error page or a rate-limit response cannot bury the log entry.
     */
    private function errorDetail(Response $response): ?string
    {
        return $response->json('errors.0.detail')
            ?? $response->json('errors.0.title')
            ?? Str::limit((string) $response->body(), 300);
    }

    private function request()
    {
        return Http::withToken(config('lemon-squeezy.api_key'))->withHeaders([
            'Accept' => 'application/vnd.api+json',
            'Content-Type' => 'application/vnd.api+json',
        ]);
    }

    private function fetchSubscriptionItemId(string $subscriptionId): ?string
    {
        $response = Http::withToken(config('lemon-squeezy.api_key'))
            ->withHeaders(['Accept' => 'application/vnd.api+json'])
            ->get(self::BASE.'/subscriptions/'.$subscriptionId);

        if (! $response->successful()) {
            Log::warning('Could not fetch Lemon Squeezy subscription', [
                'subscription_id' => $subscriptionId,
                'status' => $response->status(),
                'error' => $this->errorDetail($response),
            ]);

            return null;
        }

        $items = $this->extractSubscriptionItems($response->json(), $subscriptionId);

        if ($items === []) {
            return null;
        }

        $item = $items[0];

        return $item['id'] ?? $item['attributes']['id'] ?? null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function extractSubscriptionItems(array $subscriptionData, string $subscriptionId): array
    {
        if (isset($subscriptionData['data']['attributes']['subscription_items'])) {
            return $subscriptionData['data']['attributes']['subscription_items'];
        }

        if (isset($subscriptionData['data']['relationships']['subscription_items']['data'])) {
            return $subscriptionData['data']['relationships']['subscription_items']['data'];
        }

        if (isset($subscriptionData['included'])) {
            return collect($subscriptionData['included'])
                ->filter(fn ($item) => ($item['type'] ?? null) === 'subscription-items')
                ->values()
                ->toArray();
        }

        $response = Http::withToken(config('lemon-squeezy.api_key'))
            ->withHeaders(['Accept' => 'application/vnd.api+json'])
            ->get(self::BASE.'/subscription-items?filter[subscription_id]='.$subscriptionId);

        if ($response->successful()) {
            return $response->json('data') ?: [];
        }

        return [];
    }
}
