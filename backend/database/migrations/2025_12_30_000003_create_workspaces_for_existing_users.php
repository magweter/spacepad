<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Models\Display;
use App\Models\Device;
use App\Models\Calendar;
use App\Models\Room;    
use App\Enums\WorkspaceRole;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Create a workspace for each existing user and migrate their data
        User::chunk(100, function ($users) {
            foreach ($users as $user) {
                // Skip if user already has a workspace
                if ($user->workspaces()->exists()) {
                    continue;
                }

                // Create workspace for user
                $workspace = Workspace::create([
                    'name' => $user->name . "'s Workspace",
                ]);

                // Add user as owner member (use WorkspaceMember::create to generate ULID)
                WorkspaceMember::create([
                    'workspace_id' => $workspace->id,
                    'user_id' => $user->id,
                    'role' => WorkspaceRole::OWNER,
                ]);

                // Migrate displays to workspace
                Display::where('user_id', $user->id)->update(['workspace_id' => $workspace->id]);

                // Migrate devices to workspace
                Device::where('user_id', $user->id)->update(['workspace_id' => $workspace->id]);

                // Migrate calendars to workspace
                Calendar::where('user_id', $user->id)->update(['workspace_id' => $workspace->id]);

                // Migrate rooms to workspace
                Room::where('user_id', $user->id)->update(['workspace_id' => $workspace->id]);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This migration cannot be fully reversed as we don't know which workspace
        // data belongs to which user after potential member additions.
        // In practice, you'd need to keep the user_id relationships intact.
    }
};

