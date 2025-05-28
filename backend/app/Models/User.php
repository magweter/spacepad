<?php

namespace App\Models;

use App\Enums\Plan;
use App\Traits\HasUlid;
use App\Traits\HasLastActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use LemonSqueezy\Laravel\Billable;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasUlid, HasLastActivity, Billable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'microsoft_id',
        'google_id',
        'status',
        'email_verified_at',
        'last_login_at',
        'last_activity_at'
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
        'last_login_at' => 'datetime',
        'last_activity_at' => 'datetime',
    ];

    public function outlookAccounts(): HasMany
    {
        return $this->hasMany(OutlookAccount::class);
    }

    public function googleAccounts(): HasMany
    {
        return $this->hasMany(GoogleAccount::class);
    }

    public function caldavAccounts(): HasMany
    {
        return $this->hasMany(CalDAVAccount::class);
    }

    public function displays(): HasMany
    {
        return $this->hasMany(Display::class);
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

    public function hasAccess(): bool
    {
        $freeTrialDays = config('settings.free_trial_days');
        $isNewerThanTrialPeriod = $this->created_at->gt(now()->subDays($freeTrialDays));
        if (config('settings.is_self_hosted') || $isNewerThanTrialPeriod || $this->subscribed()) {
            return true;
        }

        return false;
    }
}
