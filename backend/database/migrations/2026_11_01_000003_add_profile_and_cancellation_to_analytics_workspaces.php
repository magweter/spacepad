<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The workspace profile answers and the latest cancellation reason, in the reporting snapshot.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Cloud only, like the table itself.
        if (config('settings.is_self_hosted') || ! Schema::hasTable('analytics_workspaces')) {
            return;
        }

        Schema::table('analytics_workspaces', function (Blueprint $table) {
            $table->string('rooms_estimate', 8)->nullable();
            $table->string('previous_solution', 32)->nullable();
            $table->string('previous_solution_other')->nullable();
            $table->string('last_cancellation_reason', 32)->nullable();
            $table->text('last_cancellation_details')->nullable();
            $table->timestamp('last_cancelled_at')->nullable();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('analytics_workspaces')) {
            return;
        }

        Schema::table('analytics_workspaces', function (Blueprint $table) {
            $table->dropColumn([
                'rooms_estimate', 'previous_solution', 'previous_solution_other',
                'last_cancellation_reason', 'last_cancellation_details', 'last_cancelled_at',
            ]);
        });
    }
};
