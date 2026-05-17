<?php

namespace App\Models;

use App\Enums\AccountStatus;
use App\Enums\OutlookBookingMethod;
use App\Enums\PermissionType;
use App\Traits\HasUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OutlookAccount extends Model
{
    use HasFactory;
    use HasUlid;

    protected $fillable = [
        'name',
        'email',
        'avatar',
        'tenant_id',
        'status',
        'permission_type',
        'user_id',
        'workspace_id',
        'outlook_id',
        'token',
        'refresh_token',
        'token_expires_at',
        'booking_method',
    ];

    protected $hidden = [
        'token',
        'refresh_token',
    ];

    protected $casts = [
        'token_expires_at' => 'datetime',
        'status' => AccountStatus::class,
        'permission_type' => PermissionType::class,
        'token' => 'encrypted',
        'refresh_token' => 'encrypted',
        'booking_method' => OutlookBookingMethod::class,
    ];

    public function isBusiness(): bool
    {
        return ! empty($this->tenant_id);
    }

    public function calendars(): HasMany
    {
        return $this->hasMany(Calendar::class, 'outlook_account_id');
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
