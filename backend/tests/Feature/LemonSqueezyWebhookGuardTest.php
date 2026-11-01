<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function subscriptionCreatedPayload(): array
{
    return [
        'meta' => [
            'event_name' => 'subscription_created',
            'custom_data' => ['billable_id' => '01HXXXXXXXXXXXXXXXXXXXXXXX', 'billable_type' => 'App\\Models\\Workspace'],
        ],
        'data' => [
            'id' => '1',
            'attributes' => [
                'customer_id' => 1,
                'status' => 'active',
                'product_id' => 1,
                'variant_id' => 1,
                'card_brand' => null,
                'card_last_four' => null,
                'trial_ends_at' => null,
                'renews_at' => null,
                'ends_at' => null,
            ],
        ],
    ];
}

/**
 * The package only verifies the webhook signature when a signing secret is configured.
 * Without one the endpoint accepted any payload, and a forged subscription_created with a
 * workspace id in custom_data handed that workspace Pro. Fail closed instead: no secret,
 * no webhook.
 */
test('the webhook is refused when no signing secret is configured', function () {
    config(['lemon-squeezy.signing_secret' => null]);

    $this->postJson('/lemon-squeezy/webhook', subscriptionCreatedPayload())
        ->assertStatus(503);
});

test('the webhook still checks the signature when a secret is configured', function () {
    config(['lemon-squeezy.signing_secret' => 'top-secret']);

    $this->postJson('/lemon-squeezy/webhook', subscriptionCreatedPayload(), ['X-Signature' => 'nope'])
        ->assertStatus(403);
});
