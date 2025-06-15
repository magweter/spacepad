<?php

namespace Database\Factories;

use App\Models\Calendar;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Calendar>
 */
class CalendarFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Calendar::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'calendar_id' => $this->faker->uuid(),
            'name' => $this->faker->word(),
            'is_primary' => false,
        ];
    }

    /**
     * Indicate that the calendar is primary.
     */
    public function primary(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_primary' => true,
        ]);
    }

    /**
     * Indicate that the calendar belongs to an Outlook account.
     */
    public function outlook(): static
    {
        return $this->state(fn (array $attributes) => [
            'outlook_account_id' => OutlookAccount::factory(),
        ]);
    }

    /**
     * Indicate that the calendar belongs to a Google account.
     */
    public function google(): static
    {
        return $this->state(fn (array $attributes) => [
            'google_account_id' => GoogleAccount::factory(),
        ]);
    }

    /**
     * Indicate that the calendar belongs to a CalDAV account.
     */
    public function caldav(): static
    {
        return $this->state(fn (array $attributes) => [
            'caldav_account_id' => CalDAVAccount::factory(),
        ]);
    }
} 