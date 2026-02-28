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
        'instance_key',
        'license_key',
        'license_valid',
        'license_expires_at',
        'is_self_hosted',
        'displays_count',
        'rooms_count',
        'boards_count',
        'users',
        'version',
        'last_validated_at',
        'last_heartbeat_at',
    ];

    protected $casts = [
        'license_valid' => 'boolean',
        'is_self_hosted' => 'boolean',
        'users' => 'array',
        'license_expires_at' => 'datetime',
        'last_validated_at' => 'datetime',
        'last_heartbeat_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
