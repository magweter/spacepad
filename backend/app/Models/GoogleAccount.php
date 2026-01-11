<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Services\GoogleService;
use App\Traits\HasUlid;
use App\Enums\AccountStatus;
use App\Enums\PermissionType;
use App\Enums\GoogleBookingMethod;

class GoogleAccount extends Model
{
    use HasFactory;
    use HasUlid;

    protected $fillable = [
        'name',
        'email',
        'avatar',
        'hosted_domain',
        'status',
        'permission_type',
        'service_account_file_path',
        'booking_method',
        'user_id',
        'workspace_id',
        'google_id',
        'token',
        'refresh_token',
        'token_expires_at',
    ];

    protected $hidden = [
        'token',
        'refresh_token',
    ];

    protected $casts = [
        'token_expires_at' => 'datetime',
        'status' => AccountStatus::class,
        'permission_type' => PermissionType::class,
        'booking_method' => GoogleBookingMethod::class,
        'token' => 'encrypted',
        'refresh_token' => 'encrypted',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function calendars(): HasMany
    {
        return $this->hasMany(Calendar::class, 'google_account_id');
    }

    public function isBusiness(): bool
    {
        return !empty($this->hosted_domain);
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
