<?php

namespace App\Models;

use App\Enums\Provider;
use App\Traits\HasUlid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventSubscription extends Model
{
    use HasFactory;
    use HasUlid;

    protected $fillable = [
        'subscription_id',
        'resource',
        'expiration',
        'notification_url',
        'user_id',
        'synchronization_id',
        'outlook_account_id',
        'google_account_id',
        'provider',
    ];

    public function scopeExpired(Builder $query)
    {
        return $query->where('expiration', '<=', now()->toAtomString());
    }

    public function connectedAccount(): ?BelongsTo
    {
        return $this->belongsTo(OutlookAccount::class, 'outlook_account_id');
    }
}
