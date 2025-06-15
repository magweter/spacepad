<?php

namespace Database\Factories;

use App\Enums\DisplayStatus;
use App\Models\Calendar;
use App\Models\Display;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Display>
 */
class DisplayFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Display::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'calendar_id' => Calendar::factory(),
            'name' => $this->faker->word(),
            'display_name' => $this->faker->word(),
            'status' => DisplayStatus::READY,
        ];
    }

    /**
     * Indicate that the display is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DisplayStatus::ACTIVE,
        ]);
    }

    /**
     * Indicate that the display is deactivated.
     */
    public function deactivated(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DisplayStatus::DEACTIVATED,
        ]);
    }
} 