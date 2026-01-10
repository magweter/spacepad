# Workspace System Documentation

## Overview

The workspace system allows multiple users to collaborate on managing displays, devices, calendars, and rooms. Each user automatically gets their own workspace, and Pro users can invite colleagues to join their workspace.

## Architecture

### Models

1. **Workspace** - Represents a team/workspace
   - Has an `owner` (User)
   - Has many `members` (Users with roles)
   - Contains displays, devices, calendars, rooms

2. **WorkspaceMember** - Pivot table linking users to workspaces
   - Roles: `owner`, `admin`, `member`
   - `owner` role is implicit for the workspace owner

### Relationships

- **User** → **Workspace** (one-to-many: owned workspaces)
- **User** ↔ **Workspace** (many-to-many: member workspaces)
- **Workspace** → **Display** (one-to-many)
- **Workspace** → **Device** (one-to-many)
- **Workspace** → **Calendar** (one-to-many)
- **Workspace** → **Room** (one-to-many)

## Migration Strategy

1. **Existing Users**: Each user automatically gets a workspace created with their name
2. **Existing Data**: All displays, devices, calendars, and rooms are migrated to the user's workspace
3. **Backward Compatibility**: The `user_id` field is kept for backward compatibility

## Permissions

### Workspace Roles

- **Owner**: Full control (can delete workspace, manage all members)
- **Admin**: Can manage members and workspace settings
- **Member**: Can view and use workspace resources

### Display Access

- Users can access displays they own directly (`user_id`)
- Users can access displays in workspaces they're members of (`workspace_id`)
- Device authentication checks workspace membership

## Usage

### Adding a Colleague

1. Navigate to workspace settings (requires Pro)
2. Enter colleague's email address
3. Select role (admin or member)
4. Colleague receives access to all workspace resources

### Managing Members

- **Add Member**: Only owners/admins can add members
- **Update Role**: Change member role between admin/member
- **Remove Member**: Remove access from workspace

## API Changes

### DisplayController

- `index()` now returns displays from user's workspace(s)
- Access checks include workspace membership

### DisplayService

- `validateDisplayPermission()` checks workspace membership
- Pro features check workspace owner's Pro status

## Frontend Changes Needed

1. **Workspace Management UI**
   - List workspaces
   - View workspace members
   - Add/remove members
   - Update member roles

2. **Display Creation**
   - Automatically assign to user's primary workspace
   - Allow selecting workspace (if user has multiple)

3. **Device Connection**
   - Connect code should work with workspace
   - Devices inherit workspace from user

## Migration Commands

Run migrations in order:

```bash
php artisan migrate
```

The migration `2025_12_30_000003_create_workspaces_for_existing_users.php` will:
1. Create a workspace for each existing user
2. Migrate all user's displays, devices, calendars, and rooms to their workspace
3. Add the user as an owner member

## Notes

- Pro subscription is required to add team members
- Workspace owner cannot be removed
- All existing functionality remains backward compatible
- `user_id` fields are kept for direct ownership tracking

