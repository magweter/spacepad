<?php

namespace App\Models;

use App\Enums\CancellationReason;
use App\Traits\HasUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionCancellation extends Model
{
    use HasUlid;

    public const SOURCE_IN_APP = 'in_app';

    public const SOURCE_PORTAL = 'portal';

    protected $fillable = [
        'workspace_id',
        'user_id',
        'lemon_squeezy_subscription_id',
        'reason',
        'details',
        'source',
        'answered_at',
        'dismissed_at',
    ];

    protected $casts = [
        'reason' => CancellationReason::class,
        'answered_at' => 'datetime',
        'dismissed_at' => 'datetime',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
