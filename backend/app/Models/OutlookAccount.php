<?php

namespace App\Models;

use App\Services\OutlookService;
use App\Traits\HasUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OutlookAccount extends Model
{
    use HasFactory;
    use HasUlid;

    protected $fillable = [
        'user_id',
        'outlook_id',
        'email',
        'name',
        'avatar',
        'token',
        'refresh_token',
        'token_expires_at',
    ];

    /**
     * @throws \Exception
     */
    public function getRooms(): array
    {
        $rooms = app(OutlookService::class)->fetchRooms($this);
        return collect($rooms)->map(function (array $room) {
            return [
                'emailAddress' => $room['emailAddress'],
                'name' => $room['displayName']
            ];
        })->toArray();
    }

    /**
     * @throws \Exception
     */
    public function getCalendars(): array
    {
        $calendars = app(OutlookService::class)->fetchCalendars($this);
        return collect($calendars)->map(function (array $calendar) {
            return [
                'id' => $calendar['id'],
                'name' => $calendar['name']
            ];
        })->toArray();
    }
}
