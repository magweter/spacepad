<?php

namespace Database\Factories;

use App\Enums\AccountStatus;
use App\Models\CalDAVAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CalDAVAccount>
 */
class CalDAVAccountFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = CalDAVAccount::class;

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
            'status' => AccountStatus::CONNECTED,
            'url' => $this->faker->url(),
            'username' => $this->faker->userName(),
            'password' => $this->faker->password(),
        ];
    }
}
