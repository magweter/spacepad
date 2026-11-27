<?php

namespace App\Services;

use App\Enums\FunnelStep;
use App\Models\FunnelEvent;
use App\Models\User;
use App\Models\Workspace;
use Carbon\CarbonInterface;

/**
 * Records the funnel in our own database, next to the Google Tag Manager events.
 *
 * Tag Manager runs in the browser, so ad blockers drop part of it and none of it can be tied
 * to a workspace. These rows can, which is what per-account and per-cohort reporting needs.
 */
class Funnel
{
    /**
     * @param  array<string, mixed>  $properties
     */
    public static function record(FunnelStep $step, ?Workspace $workspace, ?User $user = null, array $properties = [], ?CarbonInterface $occurredAt = null): FunnelEvent
    {
        return FunnelEvent::create([
            'workspace_id' => $workspace?->id,
            'user_id' => $user?->id,
            'event' => $step->value,
            'properties' => $properties ?: null,
            'occurred_at' => $occurredAt ?? now(),
        ]);
    }

    /**
     * Record a milestone only the first time a workspace reaches it.
     *
     * @param  array<string, mixed>  $properties
     */
    public static function recordOnce(FunnelStep $step, ?Workspace $workspace, ?User $user = null, array $properties = [], ?CarbonInterface $occurredAt = null): void
    {
        if (! $workspace || self::reached($step, $workspace)) {
            return;
        }

        self::record($step, $workspace, $user, $properties, $occurredAt);
    }

    public static function reached(FunnelStep $step, Workspace $workspace): bool
    {
        return FunnelEvent::query()
            ->where('workspace_id', $workspace->id)
            ->where('event', $step->value)
            ->exists();
    }
}
