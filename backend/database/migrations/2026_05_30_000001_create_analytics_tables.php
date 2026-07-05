<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (config('settings.is_self_hosted')) {
            return;
        }

        Schema::create('analytics_users', function (Blueprint $table) {
            $table->id();
            $table->ulid('user_id')->unique();
            $table->string('email');
            $table->string('name');
            $table->timestamp('registered_at')->nullable();
            $table->timestamp('last_user_activity_at')->nullable();
            $table->timestamp('last_device_activity_at')->nullable();
            $table->boolean('is_unlimited')->default(false);
            $table->integer('displays_count')->default(0);
            $table->integer('boards_count')->default(0);
            $table->integer('rooms_count')->default(0);
            $table->string('subscription_status')->nullable();  // active, on_trial, past_due, paused, cancelled, expired, unlimited, none
            $table->string('billing_interval')->nullable();     // monthly, yearly
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('subscription_ends_at')->nullable();
            $table->timestamp('subscription_renews_at')->nullable();
            $table->string('lemon_squeezy_id')->nullable();
            $table->decimal('mrr_current', 10, 2)->default(0);   // active subscriptions only
            $table->decimal('mrr_expected', 10, 2)->default(0);  // active + trial
            $table->timestamp('refreshed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('analytics_instances', function (Blueprint $table) {
            $table->id();
            $table->ulid('instance_id')->unique();
            $table->string('instance_key');
            $table->string('version')->nullable();
            $table->integer('displays_count')->nullable();
            $table->integer('rooms_count')->nullable();
            $table->integer('boards_count')->nullable();
            $table->boolean('is_paid')->default(false);
            $table->timestamp('license_expires_at')->nullable();
            $table->timestamp('last_heartbeat_at')->nullable();
            $table->timestamp('last_validated_at')->nullable();
            $table->timestamp('registered_at')->nullable();
            $table->decimal('mrr_current', 10, 2)->default(0);
            $table->decimal('mrr_expected', 10, 2)->default(0);
            $table->timestamp('refreshed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        if (config('settings.is_self_hosted')) {
            return;
        }

        Schema::dropIfExists('analytics_users');
        Schema::dropIfExists('analytics_instances');
    }
};
