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
        'display_id',
        'outlook_account_id',
    ];

    public function scopeExpired(Builder $query)
    {
        return $query->where('expiration', '<=', now()->toAtomString());
    }

    public function outlookAccount(): BelongsTo
    {
        return $this->belongsTo(OutlookAccount::class, 'outlook_account_id');
    }

    public function display(): BelongsTo
    {
        return $this->belongsTo(Display::class, 'display_id');
    }
}
