<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Two optional questions a workspace answers after its first display: how many rooms it
 * wants to show, and what it used before.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->string('rooms_estimate', 8)->nullable();
            $table->string('previous_solution', 32)->nullable();
            $table->string('previous_solution_other')->nullable();
            $table->timestamp('profile_prompted_at')->nullable();
            $table->timestamp('profile_answered_at')->nullable();
            $table->timestamp('profile_dismissed_at')->nullable();
        });

    }

    public function down(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->dropColumn([
                'rooms_estimate', 'previous_solution', 'previous_solution_other',
                'profile_prompted_at', 'profile_answered_at', 'profile_dismissed_at',
            ]);
        });

    }
};
