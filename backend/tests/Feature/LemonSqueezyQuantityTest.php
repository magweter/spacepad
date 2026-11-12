<?php

use App\Services\LemonSqueezyUsageService;
use Illuminate\Support\Facades\Http;

/**
 * Reading and setting the quantity of a subscription item, the way the yearly billing
 * needs it: with an immediate invoice, or without any proration at all.
 */
beforeEach(function () {
    config(['lemon-squeezy.api_key' => 'test-key']);
    cache()->flush();
});

test('the quantity is read from the subscription item', function () {
    Http::fake([
        '*lemonsqueezy.com/v1/subscription-items/item_1' => Http::response([
            'data' => ['id' => 'item_1', 'attributes' => ['quantity' => 7]],
        ]),
    ]);

    expect(app(LemonSqueezyUsageService::class)->getQuantity('item_1'))->toBe(7);
});

test('an unreadable item gives no quantity', function () {
    Http::fake(['*' => Http::response([], 500)]);

    expect(app(LemonSqueezyUsageService::class)->getQuantity('item_1'))->toBeNull();
});

test('a quantity can be invoiced immediately', function () {
    Http::fake(['*' => Http::response(['data' => []])]);

    expect(app(LemonSqueezyUsageService::class)->updateQuantity('item_1', 9, invoiceImmediately: true))->toBeTrue();

    Http::assertSent(fn ($request) => $request->method() === 'PATCH'
        && str_ends_with($request->url(), 'subscription-items/item_1')
        && $request['data']['attributes'] === ['quantity' => 9, 'invoice_immediately' => true]);
});

test('a quantity can be set without proration', function () {
    Http::fake(['*' => Http::response(['data' => []])]);

    app(LemonSqueezyUsageService::class)->updateQuantity('item_1', 4, disableProrations: true);

    Http::assertSent(fn ($request) => $request['data']['attributes'] === ['quantity' => 4, 'disable_prorations' => true]);
});

test('a refused update reports failure', function () {
    Http::fake(['*' => Http::response([], 422)]);

    expect(app(LemonSqueezyUsageService::class)->updateQuantity('item_1', 4))->toBeFalse();
});

test('the cached item id can be forgotten', function () {
    cache()->put('lemonsqueezy:subscription:sub_1:item-id', 'item_old', now()->addDay());

    app(LemonSqueezyUsageService::class)->forgetSubscriptionItemId('sub_1');

    expect(cache()->has('lemonsqueezy:subscription:sub_1:item-id'))->toBeFalse();
});
