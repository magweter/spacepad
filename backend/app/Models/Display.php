<?php

namespace App\Models;

use App\Traits\HasUlid;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Enums\DisplayStatus;
use App\Helpers\DisplaySettings;

class Display extends Model
{
    use HasFactory;
    use HasUlid;

    protected $fillable = [
        'user_id',
        'name',
        'display_name',
        'calendar_id',
        'status',
        'last_sync_at',
        'last_event_at'
    ];

    protected $casts = [
        'last_sync_at' => 'datetime',
        'last_event_at' => 'datetime',
        'status' => DisplayStatus::class,
    ];

    public function calendar(): BelongsTo
    {
        return $this->belongsTo(Calendar::class, 'calendar_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function eventSubscriptions(): HasMany
    {
        return $this->hasMany(EventSubscription::class);
    }

    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }

    public function settings(): HasMany
    {
        return $this->hasMany(DisplaySetting::class);
    }

    public function getStartTime(): Carbon
    {
        return now()->startOfDay();
    }

    public function getEndTime(): Carbon
    {
        return now()->endOfDay();
    }

    public function getEventsCacheKey(): string
    {
        return "display:$this->id:events";
    }

    public function isDeactivated(): bool
    {
        return $this->status === DisplayStatus::DEACTIVATED;
    }

    public function updateLastEventAt(Carbon|null $date = null): void
    {
        $this->update(['last_event_at' => $date ?? now()]);
    }

    public function updateLastSyncAt(Carbon|null $date = null): void
    {
        $this->update(['last_sync_at' => $date ?? now()]);
    }

    // Display settings convenience methods
    public function isCheckInEnabled(): bool
    {
        return DisplaySettings::isCheckInEnabled($this);
    }

    public function isBookingEnabled(): bool
    {
        return DisplaySettings::isBookingEnabled($this);
    }

    public function setCheckInEnabled(bool $enabled): bool
    {
        return DisplaySettings::setCheckInEnabled($this, $enabled);
    }

    public function setBookingEnabled(bool $enabled): bool
    {
        return DisplaySettings::setBookingEnabled($this, $enabled);
    }
}
