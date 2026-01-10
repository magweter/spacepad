<?php

namespace App\Models;

use App\Enums\WorkspaceRole;
use App\Traits\HasUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Workspace extends Model
{
    use HasFactory, HasUlid;

    protected $fillable = [
        'name',
    ];

    /**
     * Get all members of the workspace
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'workspace_members')
            ->withPivot('role')
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
        if (!$member) {
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
        if (!$member) {
            return null;
        }
        
        $role = $member->pivot->role;
        return $role instanceof WorkspaceRole ? $role : WorkspaceRole::from($role);
    }
}

