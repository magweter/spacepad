<?php

namespace App\Models;

use App\Enums\Plan;
use App\Traits\HasUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasUlid;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'azure_ad_id',
        'plan_id',
        'status',
        'email_verified_at'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function outlookAccounts(): HasMany
    {
        return $this->hasMany(OutlookAccount::class);
    }

    public function displays(): HasMany
    {
        return $this->hasMany(Display::class);
    }

    public function canCreateDisplays(): bool
    {
        return $this->displays()->count() < 1;
    }

    public function getConnectCode(): string
    {
        $connectCode = cache()->get("user:$this->id:connect-code");
        if (!$connectCode) {
            $expiresAt = now()->addMinutes(30);
            do {
                $connectCode = mt_rand(100000, 999999);
            } while (cache()->has("connect-code:$connectCode"));

            cache()->put("user:$this->id:connect-code", $connectCode, $expiresAt);
            cache()->put("connect-code:$connectCode", auth()->id(), $expiresAt);
        }

        return $connectCode;
    }
}
