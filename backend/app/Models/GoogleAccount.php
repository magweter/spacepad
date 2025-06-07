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
}
