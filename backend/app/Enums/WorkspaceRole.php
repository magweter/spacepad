<?php

namespace App\Enums;

enum WorkspaceRole: string
{
    case OWNER = 'owner';
    case ADMIN = 'admin';
    case MEMBER = 'member';

    public function label(): string
    {
        return match ($this) {
            self::OWNER => 'Owner',
            self::ADMIN => 'Admin',
            self::MEMBER => 'Member',
        };
    }

    /**
     * Check if this role can manage the workspace
     */
    public function canManage(): bool
    {
        return in_array($this, [self::OWNER, self::ADMIN]);
    }

    /**
     * Manage the things inside the workspace: displays, boards, profiles, calendar
     * accounts and their settings. Every role may do this — that is the point of sharing a
     * workspace with colleagues.
     */
    public function canManageContent(): bool
    {
        return true;
    }

    /**
     * Invite new members and withdraw pending invitations.
     */
    public function canInvite(): bool
    {
        return in_array($this, [self::OWNER, self::ADMIN]);
    }

    /**
     * Change an existing member's role or remove them. Owner-only, so an admin cannot
     * lock out the owner or promote themselves.
     */
    public function canManageMembers(): bool
    {
        return $this === self::OWNER;
    }

    /**
     * Start a checkout, open the billing portal, change the subscription.
     */
    public function canManageBilling(): bool
    {
        return $this === self::OWNER;
    }

    /**
     * Normalize a pivot value into a role.
     *
     * Workspace::members() declares withPivot('role') without a cast, so
     * $member->pivot->role is a raw string there, while WorkspaceMember casts it to this
     * enum. Route every comparison through here instead of comparing against ->value.
     */
    public static function fromPivot(mixed $value): self
    {
        return $value instanceof self ? $value : self::from($value);
    }
}
