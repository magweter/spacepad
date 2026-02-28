<?php

namespace Database\Factories;

use App\Models\Instance;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Instance>
 */
class InstanceFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Instance::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'instance_key' => $this->faker->sha1(),
            'license_key' => null,
            'license_valid' => false,
            'license_expires_at' => null,
            'is_self_hosted' => true,
            'displays_count' => $this->faker->numberBetween(0, 10),
            'rooms_count' => $this->faker->numberBetween(0, 5),
            'boards_count' => null,
            'users' => [
                [
                    'email' => $this->faker->safeEmail(),
                    'usage_type' => 'personal',
                ],
            ],
            'version' => '1.0.0',
            'last_validated_at' => now(),
            'last_heartbeat_at' => now(),
        ];
    }
}
