<?php

namespace Database\Factories;

use App\Models\Board;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Board>
 */
class BoardFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Board::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'user_id' => User::factory(),
            'name' => $this->faker->words(3, true),
            'title' => null,
            'subtitle' => null,
            'show_all_displays' => true,
            'theme' => 'dark',
            'logo' => null,
            'show_title' => true,
            'show_booker' => true,
            'show_next_event' => true,
            'show_transitioning' => true,
            'transitioning_minutes' => 10,
            'font_family' => 'Inter',
            'language' => 'en',
            'view_mode' => 'card',
            'show_meeting_title' => true,
        ];
    }
}
