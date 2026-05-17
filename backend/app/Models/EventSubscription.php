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
        'google_account_id',
        'retry_count',
        'last_retry_at',
        'next_retry_at',
    ];

    public function scopeExpired(Builder $query)
    {
        return $query->where('expiration', '<=', now()->toAtomString());
    }

    public function outlookAccount(): BelongsTo
    {
        return $this->belongsTo(OutlookAccount::class, 'outlook_account_id');
    }

    public function googleAccount(): BelongsTo
    {
        return $this->belongsTo(GoogleAccount::class, 'google_account_id');
    }

    public function display(): BelongsTo
    {
        return $this->belongsTo(Display::class, 'display_id');
    }

    /**
     * Calculate the next retry delay in minutes using exponential backoff.
     * 1st retry: 1 min, 2nd: 5 min, 3rd: 15 min, 4th: 30 min, 5th+: 60 min (never gives up)
     */
    public function calculateNextRetryDelay(): int
    {
        return match ($this->retry_count) {
            0 => 1,      // First retry after 1 minute
            1 => 5,      // Second retry after 5 minutes
            2 => 15,     // Third retry after 15 minutes
            3 => 30,     // Fourth retry after 30 minutes
            default => 60, // Keep retrying every 60 minutes indefinitely
        };
    }

    /**
     * Check if this subscription is ready for retry.
     */
    public function scopeReadyForRetry(Builder $query)
    {
        return $query->whereNotNull('next_retry_at')
            ->where('next_retry_at', '<=', now());
    }

    /**
     * Reset retry tracking after successful creation.
     */
    public function resetRetry(): void
    {
        $this->update([
            'retry_count' => 0,
            'last_retry_at' => null,
            'next_retry_at' => null,
        ]);
    }

    /**
     * Increment retry count and schedule next retry.
     */
    public function incrementRetry(): void
    {
        $delayMinutes = $this->calculateNextRetryDelay();

        $this->update([
            'retry_count' => $this->retry_count + 1,
            'last_retry_at' => now(),
            'next_retry_at' => now()->addMinutes($delayMinutes),
        ]);
    }
}
