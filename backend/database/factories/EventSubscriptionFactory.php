<?php

namespace Database\Factories;

use App\Models\EventSubscription;
use App\Models\GoogleAccount;
use App\Models\OutlookAccount;
use App\Models\Display;
use Illuminate\Database\Eloquent\Factories\Factory;

class EventSubscriptionFactory extends Factory
{
    protected $model = EventSubscription::class;

    public function definition(): array
    {
        return [
            'subscription_id' => $this->faker->uuid,
            'resource' => 'me/events',
            'expiration' => now()->addDays(3),
            'notification_url' => config('services.azure_ad.webhook_url'),
            'display_id' => Display::factory(),
            'outlook_account_id' => null,
            'google_account_id' => null,
        ];
    }

    public function outlook(OutlookAccount $account): self
    {
        return $this->state(function (array $attributes) use ($account) {
            return [
                'outlook_account_id' => $account->id,
                'google_account_id' => null,
            ];
        });
    }

    public function google(GoogleAccount $account): self
    {
        return $this->state(function (array $attributes) use ($account) {
            return [
                'outlook_account_id' => null,
                'google_account_id' => $account->id,
            ];
        });
    }
} 