<?php

namespace App\Models;

use App\Enums\AccountStatus;
use App\Traits\HasUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Services\CalDAVService;

class CalDAVAccount extends Model
{
    use HasFactory;
    use HasUlid;

    protected $table = 'caldav_accounts';

    protected $fillable = [
        'name',
        'email',
        'avatar',
        'status',
        'user_id',
        'url',
        'username',
        'password',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'status' => AccountStatus::class,
        'password' => 'encrypted',
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
            $calendars = app(CalDAVService::class)->fetchCalendars($this);
            return collect($calendars)->map(function ($calendar) {
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
