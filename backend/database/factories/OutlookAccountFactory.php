<?php

namespace Database\Factories;

use App\Enums\AccountStatus;
use App\Models\OutlookAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\OutlookAccount>
 */
class OutlookAccountFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = OutlookAccount::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'avatar' => $this->faker->imageUrl(),
            'tenant_id' => $this->faker->uuid(),
            'status' => AccountStatus::CONNECTED,
            'outlook_id' => $this->faker->uuid(),
            'token' => $this->faker->uuid(),
            'refresh_token' => $this->faker->uuid(),
            'token_expires_at' => now()->addHour(),
        ];
    }

    /**
     * Indicate that the account is a business account.
     */
    public function business(): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $this->faker->uuid(),
        ]);
    }
}
