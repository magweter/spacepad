<?php

namespace App\Listeners;

use App\Enums\FunnelStep;
use App\Events\UserRegistered;
use App\Services\Funnel;

/**
 * The first step of the funnel, dated when the account was created.
 */
class RecordSignupInFunnel
{
    public function handle(UserRegistered $event): void
    {
        Funnel::recordOnce(
            FunnelStep::SIGNED_UP,
            $event->user->primaryWorkspace(),
            $event->user,
            occurredAt: $event->user->created_at,
        );
    }
}
