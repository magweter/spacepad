<?php

namespace App\Models;

use App\Traits\HasUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LemonSqueezy\Laravel\Billable;

class Instance extends Model
{
    use HasFactory, HasUlid, Billable;

    protected $fillable = [
        'instance_id',
        'license_key',
        'is_self_hosted',
        'accounts',
        'users',
        'last_heartbeat_at',
        'version',
    ];

    protected $casts = [
        'accounts' => 'array',
        'users' => 'array',
        'is_self_hosted' => 'boolean',
        'last_heartbeat_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

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
