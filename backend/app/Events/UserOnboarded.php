<?php

namespace App\Events;

use App\Models\Display;
use App\Models\User;
use Illuminate\Queue\SerializesModels;

class UserOnboarded
{
    use SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(public User $user, public Display $display)
    {
        //
    }
}
