<?php

namespace App\Models;

use App\Services\OutlookService;
use App\Traits\HasUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Enums\AccountStatus;
use App\Enums\PermissionType;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
    ];

    public function isBusiness(): bool
    {
        return !empty($this->tenant_id);
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
