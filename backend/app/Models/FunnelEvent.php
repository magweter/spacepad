<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A step a workspace took on the way from signing up to paying. Written by App\Services\Funnel.
 */
class FunnelEvent extends Model
{
    protected $fillable = [
        'workspace_id',
        'user_id',
        'event',
        'properties',
        'occurred_at',
    ];

    protected $casts = [
        'properties' => 'array',
        'occurred_at' => 'datetime',
    ];
}
