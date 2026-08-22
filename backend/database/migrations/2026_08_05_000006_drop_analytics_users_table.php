<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Retire the per-user analytics snapshot.
 *
 * analytics_workspaces replaced it: same figures, keyed on the thing that actually holds a
 * subscription. Nothing in the application has read this table since.
 *
 * Deliberately the last migration of the set and in a file of its own, because Metabase
 * reads these tables directly. Hold this one back a deploy while those dashboards are
 * repointed; everything else works with it still present.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (config('settings.is_self_hosted')) {
            return;
        }

        Schema::dropIfExists('analytics_users');
    }

    public function down(): void
    {
        if (config('settings.is_self_hosted')) {
            return;
        }

        // Recreated empty. The data it held was a snapshot that the next scheduled run
        // rebuilt from scratch anyway, so there is nothing here worth reconstructing.
        Schema::create('analytics_users', function (Blueprint $table) {
            $table->id();
            $table->ulid('user_id')->unique();
            $table->ulid('workspace_id')->nullable();
            $table->string('workspace_name')->nullable();
            $table->string('email');
            $table->string('name');
            $table->timestamp('registered_at')->nullable();
            $table->timestamp('last_user_activity_at')->nullable();
            $table->timestamp('last_device_activity_at')->nullable();
            $table->boolean('is_unlimited')->default(false);
            $table->integer('displays_count')->default(0);
            $table->integer('boards_count')->default(0);
            $table->integer('rooms_count')->default(0);
            $table->string('subscription_status')->default('none');
            $table->string('billing_interval')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('subscription_ends_at')->nullable();
            $table->timestamp('subscription_renews_at')->nullable();
            $table->string('lemon_squeezy_id')->nullable();
            $table->decimal('mrr_current', 10, 2)->default(0);
            $table->decimal('mrr_expected', 10, 2)->default(0);
            $table->timestamp('refreshed_at')->nullable();
            $table->timestamps();
        });
    }
};
