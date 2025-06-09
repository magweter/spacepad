<?php

namespace App\Models;

use App\Traits\HasUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Instance extends Model
{
    use HasFactory, HasUlid;

    protected $fillable = [
        'instance_id',
        'license_key',
        'num_displays',
        'email_domain',
        'calendar_provider',
        'version',
        'last_heartbeat_at',
        'activated_at',
        'is_telemetry_enabled',
    ];

    protected $casts = [
        'last_heartbeat_at' => 'datetime',
        'activated_at' => 'datetime',
        'is_telemetry_enabled' => 'boolean',
    ];

    public function isActive(): bool
    {
        return $this->last_heartbeat_at && $this->last_heartbeat_at->diffInHours(now()) < 48;
    }

    public function hasValidLicense(): bool
    {
        return $this->license_key !== null;
    }

    public function isOverLimit(): bool
    {
        if (!$this->hasValidLicense()) {
            return $this->num_displays > 1;
        }

        // TODO: Implement license validation with LemonSqueezy
        return false;
    }
} 