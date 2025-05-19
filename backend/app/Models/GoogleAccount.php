<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Services\GoogleService;
use App\Traits\HasUlid;
use App\Enums\AccountStatus;

class GoogleAccount extends Model
{
    use HasFactory;
    use HasUlid;

    protected $fillable = [
        'name',
        'email',
        'avatar',
        'status',
        'user_id',
        'google_id',
        'token',
        'refresh_token',
        'token_expires_at',
    ];

    protected $casts = [
        'token_expires_at' => 'datetime',
        'status' => AccountStatus::class,
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function calendars(): HasMany
    {
        return $this->hasMany(Calendar::class);
    }

    public function getCalendars(): array
    {
        try {
            $calendars = app(GoogleService::class)->fetchCalendars($this);
            return collect($calendars)->map(function ($calendar) {
                return [
                    'id' => $calendar->getId(),
                    'name' => $calendar->getSummary(),
                ];
            })->toArray();
        } catch (\Exception $e) {
            report($e);
        }

        return [];
    }

    public function getRooms(): array
    {
        try {
            $rooms = app(GoogleService::class)->fetchRooms($this);
            return collect($rooms)->map(function ($room) {
                return [
                    'emailAddress' => $room->getResourceEmail(),
                    'name' => $room->getResourceName(),
                ];
            })->toArray();
        } catch (\Exception $e) {
            report($e);
        }

        return [];
    }
} 