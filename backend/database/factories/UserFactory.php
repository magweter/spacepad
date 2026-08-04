<?php

namespace Database\Factories;

use App\Enums\UsageType;
use App\Enums\UserStatus;
use App\Models\OutlookAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'status' => UserStatus::ONBOARDING,
            'is_unlimited' => false,
            'terms_accepted_at' => null,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the user is active and has an Outlook account.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => UserStatus::ACTIVE,
            'usage_type' => UsageType::PERSONAL,
            'terms_accepted_at' => now(),
        ])->afterCreating(function ($user) {
            // Stamp the workspace too. Without it the account is invisible to every
            // workspace-scoped query and policy, which silently baked the
            // nullable-workspace_id trap into every fixture.
            OutlookAccount::factory()->create([
                'user_id' => $user->id,
                'workspace_id' => $user->primaryWorkspace()?->id,
            ]);
        });
    }
}
