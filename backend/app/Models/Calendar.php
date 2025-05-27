<?php

namespace App\Models;

use App\Enums\Provider;
use App\Traits\HasUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Calendar extends Model
{
    use HasFactory;
    use HasUlid;

    protected $fillable = [
        'user_id',
        'outlook_account_id',
        'google_account_id',
        'caldav_account_id',
        'calendar_id',
        'name',
        'is_primary',
    ];

    public function outlookAccount(): ?BelongsTo
    {
        return $this->belongsTo(OutlookAccount::class, 'outlook_account_id');
    }

    public function googleAccount(): ?BelongsTo
    {
        return $this->belongsTo(GoogleAccount::class, 'google_account_id');
    }

    public function caldavAccount(): ?BelongsTo
    {
        return $this->belongsTo(CalDAVAccount::class, 'caldav_account_id');
    }

    public function room(): HasOne
    {
        return $this->hasOne(Room::class);
    }
}
