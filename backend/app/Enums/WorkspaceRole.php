<?php

namespace App\Enums;

enum WorkspaceRole: string
{
    case OWNER = 'owner';
    case ADMIN = 'admin';
    case MEMBER = 'member';

    public function label(): string
    {
        return match($this) {
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
}

