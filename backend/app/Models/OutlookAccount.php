<?php

namespace App\Models;

use App\Services\OutlookService;
use App\Traits\HasUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Enums\AccountStatus;
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

    public function isBusiness(): bool
    {
        return !empty($this->tenant_id);
    }

    public function calendars(): HasMany
    {
        return $this->hasMany(Calendar::class, 'outlook_account_id');
    }
}
