<?php

namespace App\Models;

use App\Enums\UsageType;
use App\Enums\WorkspaceRole;
use App\Services\InstanceService;
use App\Traits\HasUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LemonSqueezy\Laravel\Billable;
use LemonSqueezy\Laravel\Checkout;

class Workspace extends Model
{
    use Billable, HasFactory, HasUlid;

    protected $fillable = [
        'name',
        'is_unlimited',
        'is_manually_billed',
        'manual_billing_unit_price',
        'billing_owner_user_id',
    ];

    protected $casts = [
        'is_unlimited' => 'boolean',
        'is_manually_billed' => 'boolean',
        'manual_billing_unit_price' => 'decimal:2',
    ];

    /**
     * Memoised billing owner, so repeated Pro checks in one request stay cheap.
     */
    private ?User $resolvedBillingOwner = null;

    /**
     * Get all members of the workspace
     */
    public function members(): BelongsToMany
    {
        // The pivot id is needed to address a specific membership from the members UI.
        return $this->belongsToMany(User::class, 'workspace_members')
            ->withPivot('id', 'role')
            ->withTimestamps();
    }

    /**
     * Get all displays in this workspace
     */
    public function displays(): HasMany
    {
        return $this->hasMany(Display::class);
    }

    /**
     * Get all devices in this workspace
     */
    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }

    /**
     * Get all calendars in this workspace
     */
    public function calendars(): HasMany
    {
        return $this->hasMany(Calendar::class);
    }

    /**
     * Get all rooms in this workspace
     */
    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }

    /**
     * Get all boards in this workspace
     */
    public function boards(): HasMany
    {
        return $this->hasMany(Board::class);
    }

    /**
     * Get all invitations ever sent for this workspace
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(WorkspaceInvitation::class);
    }

    /**
     * Get all display profiles in this workspace
     */
    public function displayProfiles(): HasMany
    {
        return $this->hasMany(DisplayProfile::class);
    }

    /**
     * Get all connected Outlook accounts in this workspace
     */
    public function outlookAccounts(): HasMany
    {
        return $this->hasMany(OutlookAccount::class);
    }

    /**
     * Get all connected Google accounts in this workspace
     */
    public function googleAccounts(): HasMany
    {
        return $this->hasMany(GoogleAccount::class);
    }

    /**
     * Get all connected CalDAV accounts in this workspace
     */
    public function caldavAccounts(): HasMany
    {
        return $this->hasMany(CalDAVAccount::class);
    }

    /**
     * Get or generate a pairing code for this workspace.
     *
     * Workspace-scoped rather than user-scoped: the dashboard used to show the *owner's*
     * code while device pairing bound the tablet to the owner's primary workspace, so a
     * member pairing a tablet from workspace B ended up with a device in workspace A.
     *
     * @return string 6-digit connect code
     */
    public function getConnectCode(User $for): string
    {
        $cacheKey = "workspace:{$this->id}:user:{$for->id}:connect-code";

        $connectCode = cache()->get($cacheKey);

        if (! $connectCode) {
            $expiresAt = now()->addMinutes(30);

            do {
                $connectCode = mt_rand(100000, 999999);
            } while (cache()->has("connect-code:$connectCode"));

            cache()->put($cacheKey, $connectCode, $expiresAt);
            cache()->put("connect-code:$connectCode", [
                'user_id' => $for->id,
                'workspace_id' => $this->id,
            ], $expiresAt);
        }

        return (string) $connectCode;
    }

    /**
     * Retrieve and invalidate a connect code atomically, so it can only be used once.
     *
     * @return array{user_id: string, workspace_id: string|null}|null
     */
    public static function pullConnectCode(string $code): ?array
    {
        $payload = cache()->pull("connect-code:$code");

        if ($payload === null) {
            return null;
        }

        // Codes minted before this became workspace-scoped stored a bare user id. The 30
        // minute TTL keeps that window tiny, but a deploy mid-pairing should still work.
        if (! is_array($payload)) {
            cache()->forget("user:$payload:connect-code");

            return ['user_id' => $payload, 'workspace_id' => null];
        }

        cache()->forget("workspace:{$payload['workspace_id']}:user:{$payload['user_id']}:connect-code");

        return $payload;
    }

    /**
     * True when the workspace holds no data at all.
     *
     * Covers every workspace-scoped table, so a single stray CalDAV account is enough to
     * make a workspace non-empty.
     */
    public function isEmpty(): bool
    {
        foreach (['displays', 'devices', 'boards', 'calendars', 'rooms', 'displayProfiles',
            'outlookAccounts', 'googleAccounts', 'caldavAccounts'] as $relation) {
            if ($this->{$relation}()->exists()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Whether a user may delete this workspace themselves.
     *
     * Only for tidying up a leftover empty workspace — typically the personal one someone
     * had before joining their company's. Deliberately narrow: it must be empty, the user
     * must own it, they must have somewhere else to go, and nobody else may be left behind.
     */
    public function canBeDeletedBy(User $user): bool
    {
        if (! $this->isOwnedBy($user)) {
            return false;
        }

        if (! $this->isEmpty()) {
            return false;
        }

        // Deleting it would silently lock out whoever else is still in it.
        if ($this->members()->count() > 1) {
            return false;
        }

        if ($this->hasActiveSubscription()) {
            return false;
        }

        return $user->workspaces()
            ->where('workspaces.id', '!=', $this->id)
            ->wherePivotIn('role', [WorkspaceRole::OWNER->value, WorkspaceRole::ADMIN->value])
            ->exists();
    }

    /**
     * Whether a paid subscription is currently attached to this workspace.
     */
    public function hasActiveSubscription(): bool
    {
        if (config('settings.is_self_hosted')) {
            return false;
        }

        return $this->subscribed();
    }

    /**
     * Check if a user is a member of this workspace
     */
    public function hasMember(User $user): bool
    {
        return $this->members()->where('user_id', $user->id)->exists();
    }

    /**
     * Get the owner(s) of the workspace (members with 'owner' role)
     */
    public function owners()
    {
        return $this->members()->wherePivot('role', WorkspaceRole::OWNER->value);
    }

    /**
     * Check if a user is the owner of this workspace
     */
    public function isOwnedBy(User $user): bool
    {
        return $this->members()->where('user_id', $user->id)->wherePivot('role', WorkspaceRole::OWNER->value)->exists();
    }

    /**
     * Check if a user can manage this workspace (owner or admin)
     */
    public function canBeManagedBy(User $user): bool
    {
        $member = $this->members()->where('user_id', $user->id)->first();
        if (! $member) {
            return false;
        }

        $role = $member->pivot->role instanceof WorkspaceRole
            ? $member->pivot->role
            : WorkspaceRole::from($member->pivot->role);

        return $role->canManage();
    }

    /**
     * Get the role of a user in this workspace
     */
    public function getUserRole(User $user): ?WorkspaceRole
    {
        $member = $this->members()->where('user_id', $user->id)->first();
        if (! $member) {
            return null;
        }

        $role = $member->pivot->role;

        return $role instanceof WorkspaceRole ? $role : WorkspaceRole::from($role);
    }

    /**
     * The user who carries billing for this workspace.
     *
     * Prefers the explicitly recorded billing owner, falling back to the earliest owner
     * membership for workspaces created before that column existed.
     */
    public function billingOwner(): ?User
    {
        if ($this->resolvedBillingOwner) {
            return $this->resolvedBillingOwner;
        }

        if ($this->billing_owner_user_id) {
            $owner = $this->billingOwnerUser;

            if ($owner) {
                return $this->resolvedBillingOwner = $owner;
            }
        }

        return $this->resolvedBillingOwner = $this->owners()
            ->orderByPivot('created_at')
            ->orderBy('users.id')
            ->first();
    }

    public function billingOwnerUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'billing_owner_user_id');
    }

    /**
     * The name Lemon Squeezy should invoice. Without this override the trait would send the
     * workspace name ("Jan's Workspace") as the customer name.
     */
    public function lemonSqueezyName(): ?string
    {
        return $this->billingOwner()?->name ?? $this->name;
    }

    public function lemonSqueezyEmail(): ?string
    {
        return $this->billingOwner()?->email;
    }

    /**
     * Whether this workspace has Pro.
     *
     * Authoritative: billing lives here now. Replaces the previous loop over owners (one
     * query per owner) with a single check on the workspace's own state.
     */
    public function hasPro(): bool
    {
        if (config('settings.is_self_hosted')) {
            // Faithful port of the old per-user rule: a PERSONAL usage type is exempt from
            // the soft limit, and an instance licence covers everyone.
            return InstanceService::hasValidLicense()
                || $this->owners()->where('usage_type', UsageType::PERSONAL->value)->exists();
        }

        return $this->is_unlimited || $this->is_manually_billed || $this->subscribed();
    }

    /**
     * Monthly list price per billable unit, falling back to the global default.
     */
    public function getManualBillingUnitPrice(): float
    {
        return (float) ($this->manual_billing_unit_price ?? config('settings.manual_billing_unit_price') ?? 0);
    }

    /**
     * Locally computed MRR for a manually-billed workspace.
     */
    public function calculateManualMrr(int $displaysCount, int $boardsCount): float
    {
        return $this->getManualBillingUnitPrice() * max(1, self::calculateUsage($displaysCount, $boardsCount));
    }

    /**
     * Billable units for a given number of displays and boards.
     *
     * The single home for this formula, which was previously repeated in five places.
     */
    public static function calculateUsage(int $displays, int $boards): int
    {
        return $displays + ($boards * 2);
    }

    /**
     * A checkout for this workspace's Pro subscription.
     *
     * Authorize before calling — only an owner should be able to start one. Not cached: the
     * vendor's Checkout::url() performs a live API call, so caching the object saved
     * nothing while making stale checkout links possible.
     */
    public function getCheckoutUrl(?string $redirectUrl = null): ?Checkout
    {
        if (config('settings.is_self_hosted')) {
            return null;
        }

        return $this->subscribe(config('settings.cloud_hosted_pro_plan_id'))
            ->redirectTo($redirectUrl ?? route('dashboard'));
    }

    /**
     * Get the total usage count for billing purposes
     * Displays count as 1x, Boards count as 2x
     */
    public function getTotalUsageCount(): int
    {
        return self::calculateUsage($this->displays()->count(), $this->boards()->count());
    }

    /**
     * Get breakdown of usage for display
     */
    public function getUsageBreakdown(): array
    {
        $displayCount = $this->displays()->count();
        $boardCount = $this->boards()->count();

        return [
            'displays' => $displayCount,
            'boards' => $boardCount,
            'board_usage' => $boardCount * 2,
            'total' => self::calculateUsage($displayCount, $boardCount),
        ];
    }
}
