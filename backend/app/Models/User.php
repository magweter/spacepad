<?php

namespace App\Models;

use App\Enums\UsageType;
use App\Enums\UserStatus;
use App\Enums\WorkspaceRole;
use App\Traits\HasLastActivity;
use App\Traits\HasUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;
use LemonSqueezy\Laravel\Billable;

class User extends Authenticatable
{
    use Billable, HasApiTokens, HasFactory, HasLastActivity, HasUlid, Notifiable;

    /**
     * Whether newly created users get their own personal workspace.
     *
     * Normally they should. An invited colleague should not: they are joining an existing
     * workspace, and an empty "Someone's Workspace" alongside it is pure confusion. Toggled
     * by User::createForInvitation().
     */
    public static bool $autoCreateWorkspace = true;

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        // Auto-create workspace when user is created
        static::created(function ($user) {
            if (! static::$autoCreateWorkspace) {
                return;
            }

            // Only create if user doesn't already have a workspace
            if (! $user->workspaces()->exists()) {
                $workspace = Workspace::create([
                    'name' => $user->name."'s Workspace",
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
        'is_manually_billed',
        'manual_billing_unit_price',
        'terms_accepted_at',
        'dpa_accepted_at',
        'is_admin',
        'skipped_onboarding_at',
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
        'is_manually_billed' => 'boolean',
        'manual_billing_unit_price' => 'decimal:2',
        'usage_type' => UsageType::class,
        'terms_accepted_at' => 'datetime',
        'dpa_accepted_at' => 'datetime',
        'skipped_onboarding_at' => 'datetime',
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

    public function boards(): HasMany
    {
        return $this->hasMany(Board::class);
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

    /**
     * Create the account for someone accepting a workspace invitation.
     *
     * Possession of the invitation token proves control of the mailbox, which is the same
     * trust level as the magic login link — so the address counts as verified and there is
     * nothing left to onboard: the workspace they are joining already has calendar
     * accounts. Without skipping onboarding, CheckUserActive would bounce them to
     * /onboarding instead of the dashboard.
     *
     * No personal workspace is created; they are joining someone else's.
     */
    public static function createForInvitation(WorkspaceInvitation $invitation): self
    {
        static::$autoCreateWorkspace = false;

        try {
            $user = static::create([
                'name' => Str::before($invitation->email, '@'),
                'email' => $invitation->email,
                'email_verified_at' => now(),
                'password' => Hash::make(Str::random(40)),
                'status' => UserStatus::ACTIVE,
                // Joining a company workspace: mirror the inviter, who has already answered
                // this question, rather than asking again.
                'usage_type' => $invitation->invitedBy?->usage_type ?? UsageType::BUSINESS,
                'terms_accepted_at' => now(),
                'dpa_accepted_at' => now(),
                'skipped_onboarding_at' => now(),
            ]);
        } finally {
            static::$autoCreateWorkspace = true;
        }

        return $user;
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
     * Get or generate a connect code for this user's primary workspace.
     *
     * @deprecated Pairing is workspace-scoped. Use Workspace::getConnectCode($user) with
     *             the workspace the tablet should end up in — going through the primary
     *             workspace is exactly the bug that put devices in the wrong place.
     *
     * @return string 6-digit connect code
     */
    public function getConnectCode(): string
    {
        $workspace = $this->primaryWorkspace();

        if (! $workspace) {
            // No workspace to pair into; fall back to a bare user-scoped code, which
            // Workspace::pullConnectCode() still understands.
            $expiresAt = now()->addMinutes(30);
            do {
                $connectCode = mt_rand(100000, 999999);
            } while (cache()->has("connect-code:$connectCode"));

            cache()->put("user:$this->id:connect-code", $connectCode, $expiresAt);
            cache()->put("connect-code:$connectCode", $this->id, $expiresAt);

            return (string) $connectCode;
        }

        return $workspace->getConnectCode($this);
    }

    /**
     * @deprecated Use Workspace::pullConnectCode(), which also resolves the workspace the
     *             code was generated for.
     *
     * @return string|null The user ID associated with the code, or null if invalid/used
     */
    public static function pullConnectCode(string $code): ?string
    {
        return Workspace::pullConnectCode($code)['user_id'] ?? null;
    }

    public function isOnboarded(): bool
    {
        // Check if user has accounts OR if any workspace they're a member of has accounts
        $hasAccounts = $this->hasAnyAccount();

        if (! $hasAccounts) {
            // Check if any workspace the user is a member of has accounts
            $workspaceIds = $this->workspaces()->pluck('workspaces.id')->toArray();
            if (! empty($workspaceIds)) {
                $workspaceAccountCount = OutlookAccount::whereIn('workspace_id', $workspaceIds)->count()
                    + GoogleAccount::whereIn('workspace_id', $workspaceIds)->count()
                    + CalDAVAccount::whereIn('workspace_id', $workspaceIds)->count();

                if ($workspaceAccountCount > 0) {
                    $hasAccounts = true;
                }
            }
        }

        $skipped = $this->skipped_onboarding_at !== null;

        if (config('settings.is_self_hosted')) {
            return $this->usage_type && $this->terms_accepted_at && ($hasAccounts || $skipped);
        }

        return $this->usage_type && ($hasAccounts || $skipped);
    }

    public function featureFlags(): HasOne
    {
        return $this->hasOne(UserFeatureFlag::class);
    }

    public function hasAdvertisementFeature(): bool
    {
        return (bool) $this->featureFlags?->advertisement;
    }

    /**
     * @deprecated Pro is a property of a workspace, not a person. Use
     *             hasProForCurrentWorkspace() or hasProForWorkspace(). Kept as a delegate
     *             so the many existing call sites keep working.
     */
    public function hasPro(): bool
    {
        return $this->hasProForCurrentWorkspace();
    }

    /**
     * Whether the workspace the user is currently looking at has Pro.
     */
    public function hasProForCurrentWorkspace(): bool
    {
        return $this->getSelectedWorkspace()?->hasPro() ?? false;
    }

    /**
     * Whether a specific workspace has Pro.
     */
    public function hasProForWorkspace(Workspace $workspace): bool
    {
        return $workspace->hasPro();
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
     * Check if the user should upgrade to Pro for the current workspace context.
     * Returns false if the user has Pro OR if the selected workspace has Pro.
     */
    public function shouldUpgradeForCurrentWorkspace(): bool
    {
        // If user has Pro for current workspace, no upgrade needed
        if ($this->hasProForCurrentWorkspace()) {
            return false;
        }

        // Self Hosted: If the user is a personal user, use a soft limit
        if (config('settings.is_self_hosted') && $this->isPersonalUser()) {
            return false;
        }

        $selectedWorkspace = $this->getSelectedWorkspace();
        if (! $selectedWorkspace) {
            // No workspace context, no upgrade needed
            return false;
        }

        // Any display in the workspace counts, not only the ones this user created. Billing
        // is per workspace, and the old creator-scoped check meant an invited member never
        // saw the upgrade prompt for a workspace that was over the free limit.
        return $selectedWorkspace->displays()->exists();
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

    /**
     * Get the currently selected workspace (from session) or default to primary workspace
     *
     * Note: This works for all users (including non-Pro users) who are members of workspaces.
     * Workspace access is based on membership, not Pro status.
     */
    public function getSelectedWorkspace(): ?Workspace
    {
        $selectedWorkspaceId = session()->get('selected_workspace_id');

        if ($selectedWorkspaceId) {
            // Validate user has access to the selected workspace (checks membership, not Pro status)
            $workspace = $this->workspaces()->find($selectedWorkspaceId);
            if ($workspace) {
                return $workspace;
            }
            // If selected workspace is invalid or user no longer has access, clear it from session
            session()->forget('selected_workspace_id');
        }

        // Default to primary workspace (first owned workspace, or first workspace user is a member of)
        return $this->primaryWorkspace();
    }
}
