<?php

namespace Database\Factories;

use App\Enums\WorkspaceRole;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<WorkspaceInvitation>
 */
class WorkspaceInvitationFactory extends Factory
{
    protected $model = WorkspaceInvitation::class;

    public function definition(): array
    {
        $plainToken = Str::random(64);

        return [
            'workspace_id' => Workspace::factory(),
            'email' => $this->faker->unique()->safeEmail(),
            'role' => WorkspaceRole::MEMBER,
            'token' => WorkspaceInvitation::hashToken($plainToken),
            'expires_at' => now()->addDays(WorkspaceInvitation::LIFETIME_DAYS),
        ];
    }

    public function expired(): static
    {
        return $this->state(fn () => ['expires_at' => now()->subDay()]);
    }

    public function accepted(): static
    {
        return $this->state(fn () => ['accepted_at' => now()]);
    }
}
