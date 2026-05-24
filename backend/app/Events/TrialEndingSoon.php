<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TrialEndingSoon
{
    use Dispatchable, SerializesModels;

    public function __construct(public User $user)
    {
        //
    }
}
