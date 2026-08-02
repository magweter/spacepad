<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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
        $response = $this->request()->patch(self::BASE."/subscription-items/{$subscriptionItemId}", [
            'data' => [
                'type' => 'subscription-items',
                'id' => $subscriptionItemId,
                'attributes' => ['quantity' => $quantity],
            ],
        ]);

        return $response->successful();
    }

    /**
     * Metered billing.
     */
    public function recordUsage(string $subscriptionItemId, int $quantity): bool
    {
        $response = $this->request()->post(self::BASE.'/usage-records', [
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

        return $response->successful();
    }

    public function hasApiKey(): bool
    {
        return (bool) config('lemon-squeezy.api_key');
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
