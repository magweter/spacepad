<?php

namespace App\Models;

use App\Traits\HasUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Event extends Model
{
    use HasUlid, HasFactory;

    protected $fillable = [
        'display_id',
        'user_id',
        'calendar_id',
        'external_id',
        'status',
        'source',
        'start',
        'end',
        'summary',
        'description',
        'location',
        'timezone',
        'checked_in_at',
    ];

    protected $casts = [
        'start' => 'datetime',
        'end' => 'datetime',
        'checked_in_at' => 'datetime',
    ];

    public function display(): BelongsTo
    {
        return $this->belongsTo(Display::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function calendar(): BelongsTo
    {
        return $this->belongsTo(Calendar::class);
    }

    /**
     * Check if this is a custom (user-created) event
     */
    public function isCustomEvent(): bool
    {
        return $this->source === 'custom';
    }

    /**
     * Check if this is an external (calendar provider) event
     */
    public function isExternalEvent(): bool
    {
        return in_array($this->source, ['google', 'outlook', 'caldav']);
    }

    /**
     * Check if event is currently active
     */
    public function isActive(): bool
    {
        $now = now();
        return $this->start <= $now && $this->end > $now;
    }

    /**
     * Check if event is upcoming (starts within next hour)
     */
    public function isUpcoming(): bool
    {
        $now = now();
        $nextHour = $now->copy()->addHour();
        return $this->start > $now && $this->start <= $nextHour;
    }

    /**
     * Check if event needs check-in (active but not checked in)
     */
    public function needsCheckIn(): bool
    {
        return $this->isActive() && !$this->is_checked_in;
    }

    /**
     * Check in to this event
     */
    public function checkIn(): void
    {
        $this->update([
            'is_checked_in' => true,
            'checked_in_at' => now(),
        ]);
    }

    /**
     * Get unique identifier for external events
     */
    public function getUniqueKey(): string
    {
        return $this->external_id ?? $this->id;
    }
}
