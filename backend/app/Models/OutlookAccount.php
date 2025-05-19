<?php

namespace App\Models;

use App\Services\OutlookService;
use App\Traits\HasUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Enums\AccountStatus;

class OutlookAccount extends Model
{
    use HasFactory;
    use HasUlid;

    protected $fillable = [
        'name',
        'email',
        'avatar',
        'status',
        'user_id',
        'outlook_id',
        'token',
        'refresh_token',
        'token_expires_at',
    ];

    protected $casts = [
        'token_expires_at' => 'datetime',
        'status' => AccountStatus::class,
    ];

    /**
     * @throws \Exception
     */
    public function getRooms(): array
    {
        try {
            $rooms = app(OutlookService::class)->fetchRooms($this);
            return collect($rooms)->map(function (array $room) {
                return [
                    'emailAddress' => $room['emailAddress'],
                    'name' => $room['displayName']
                ];
            })->toArray();
        } catch (\Exception $e) {
            report($e);
        }

        return [];
    }

    /**
     * @throws \Exception
     */
    public function getCalendars(): array
    {
        try {
            $calendars = app(OutlookService::class)->fetchCalendars($this);
            return collect($calendars)->map(function (array $calendar) {
                return [
                    'id' => $calendar['id'],
                    'name' => $calendar['name']
                ];
            })->toArray();
        } catch (\Exception $e) {
            report($e);
        }

        return [];
    }
}
