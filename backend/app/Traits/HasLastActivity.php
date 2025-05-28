<?php

namespace App\Traits;

trait HasLastActivity
{
    /**
     * Update the model's last activity timestamp
     */
    public function updateLastActivity(): void
    {
        $this->update(['last_activity_at' => now()]);
    }
} 