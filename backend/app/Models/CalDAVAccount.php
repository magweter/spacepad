<?php

namespace App\Models;

use App\Enums\AccountStatus;
use App\Enums\PermissionType;
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
        'permission_type',
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
        'permission_type' => PermissionType::class,
        'password' => 'encrypted',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function calendars(): HasMany
    {
        return $this->hasMany(Calendar::class, 'caldav_account_id');
    }
}
