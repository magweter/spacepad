<?php

namespace Database\Factories;

use App\Models\PortalAlert;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PortalAlert>
 */
class PortalAlertFactory extends Factory
{
    protected $model = PortalAlert::class;

    public function definition(): array
    {
        return [
            'title' => 'Calendar provider outage',
            'body' => 'Meetings may load slowly or not at all. We are keeping an eye on it.',
            'tone' => 'attention',
            'provider' => null,
            'popup' => false,
            'starts_at' => null,
            'ends_at' => null,
        ];
    }

    public function scheduled(): static
    {
        return $this->state(['starts_at' => now()->addHour()]);
    }

    public function ended(): static
    {
        return $this->state(['starts_at' => now()->subHours(3), 'ends_at' => now()->subHour()]);
    }

    public function forProvider(string $provider): static
    {
        return $this->state(['provider' => $provider]);
    }
}
