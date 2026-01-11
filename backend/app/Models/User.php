<?php

namespace App\Models;

use App\Enums\Plan;
use App\Enums\UsageType;
use App\Enums\WorkspaceRole;
use App\Traits\HasUlid;
use App\Traits\HasLastActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use LemonSqueezy\Laravel\Billable;
use LemonSqueezy\Laravel\Checkout;
use App\Services\InstanceService;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasUlid, HasLastActivity, Billable;

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        // Auto-create workspace when user is created
        static::created(function ($user) {
            // Only create if user doesn't already have a workspace
            if (!$user->workspaces()->exists()) {
                $workspace = Workspace::create([
                    'name' => $user->name . "'s Workspace",
                ]);

                // Add user as owner member (use WorkspaceMember::create to generate ULID)
                WorkspaceMember::create([
                    'workspace_id' => $workspace->id,
                    'user_id' => $user->id,
                    'role' => WorkspaceRole::OWNER,
                ]);
            }
        });
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'first_name',
        'last_name',
        'email',
        'password',
        'microsoft_id',
        'google_id',
        'status',
        'usage_type',
        'email_verified_at',
        'last_activity_at',
        'is_unlimited',
        'terms_accepted_at',
        'is_admin',
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
        'last_activity_at' => 'datetime',
        'is_unlimited' => 'boolean',
        'usage_type' => UsageType::class,
        'terms_accepted_at' => 'datetime',
        'is_admin' => 'boolean',
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

    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }

    /**
     * Get workspaces owned by this user (where user has 'owner' role)
     */
    public function ownedWorkspaces()
    {
        return $this->workspaces()->wherePivot('role', WorkspaceRole::OWNER->value);
    }

    /**
     * Get workspaces this user is a member of
     */
    public function workspaces(): BelongsToMany
    {
        return $this->belongsToMany(Workspace::class, 'workspace_members')
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * Get the primary workspace for this user (first workspace where user is owner)
     */
    public function primaryWorkspace(): ?Workspace
    {
        return $this->ownedWorkspaces()->first() ?? $this->workspaces()->first();
    }

    /**
     * Get all workspaces this user has access to
     */
    public function accessibleWorkspaces()
    {
        return $this->workspaces()->get();
    }

    public function hasAnyDisplay(): bool
    {
        return $this->displays()->count() > 0;
    }

    public function hasAnyAccount(): bool
    {
        return $this->outlookAccounts()->count() > 0 || $this->googleAccounts()->count() > 0 || $this->caldavAccounts()->count() > 0;
    }

    /**
     * Get or generate a connect code for this user
     * 
     * @return string 6-digit connect code
     */
    public function getConnectCode(): string
    {
        $connectCode = cache()->get("user:$this->id:connect-code");
        if (!$connectCode) {
            $expiresAt = now()->addMinutes(30);
            do {
                $connectCode = mt_rand(100000, 999999);
            } while (cache()->has("connect-code:$connectCode"));

            cache()->put("user:$this->id:connect-code", $connectCode, $expiresAt);
            cache()->put("connect-code:$connectCode", $this->id, $expiresAt);
        }

        return $connectCode;
    }

    public function isOnboarded(): bool
    {
        if (config('settings.is_self_hosted')) {
            return $this->usage_type && $this->terms_accepted_at && $this->hasAnyAccount();
        }

        return $this->usage_type && $this->hasAnyAccount();
    }

    public function hasPro(): bool
    {
        if (config('settings.is_self_hosted')) {
            return $this->usage_type === UsageType::PERSONAL || InstanceService::hasValidLicense();
        }

        return $this->is_unlimited || $this->subscribed();
    }

    /**
     * Check if the user should be treated as a business user
     */
    public function isBusinessUser(): bool
    {
        return $this->usage_type === UsageType::BUSINESS;
    }

    /**
     * Check if the user should be treated as a personal user
     */
    public function isPersonalUser(): bool
    {
        return $this->usage_type === UsageType::PERSONAL;
    }

    /**
     * Check if the user should upgrade to Pro
     */
    public function shouldUpgrade(): bool
    {
        // Self Hosted: If the user is a personal user, use a soft limit
        if (config('settings.is_self_hosted') && $this->isPersonalUser()) {
            return false;
        }

        // Cloud Hosted: If the user is a business user and doesn't have Pro, they should upgrade
        return ! $this->hasPro() && $this->hasAnyDisplay();
    }

    public function getCheckoutUrl(?string $redirectUrl = null): ?Checkout
    {
        $redirectUrl ??= route('dashboard');

        if (config('settings.is_self_hosted')) {
            return null;
        }

        $cacheKey = "user:{$this->id}:checkout-url:{$redirectUrl}";

        return cache()->remember($cacheKey, now()->addHour(), function () use ($redirectUrl) {
            return auth()->user()->subscribe(config('settings.cloud_hosted_pro_plan_id'))->redirectTo($redirectUrl);
        });
    }

    /**
     * Check if the given email is allowed based on config('settings.allowed_logins')
     */
    public static function isAllowedLogin(string $email): bool
    {
        $allowed = config('settings.allowed_logins', []);
        if (empty($allowed)) {
            return true; // No restrictions set
        }

        $email = strtolower(trim($email));
        $domain = substr(strrchr($email, '@'), 1);
        foreach ($allowed as $allowedEntry) {
            $allowedEntry = strtolower($allowedEntry);
            if ($allowedEntry === $email || $allowedEntry === $domain) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if the user is an admin
     */
    public function isAdmin(): bool
    {
        return (bool) $this->is_admin;
    }
}
