<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The reporting snapshot, keyed on what we actually invoice.
 *
 * analytics_users kept one row per person, which was right when a subscription belonged
 * to a user. It stopped being right the moment colleagues could share a workspace: the
 * same displays, and the same MRR, appeared once per member. Working around that meant
 * per-membership rows and an is_billing_owner flag deciding which one of them was real,
 * so every query had to know the trick.
 *
 * One row per workspace instead. The billing contact rides along as three plain columns,
 * because "who do we email about this invoice" is a property of the invoice.
 *
 * `units` is stored resolved rather than left as displays and boards for the reader to
 * combine, so Metabase does not have to carry a copy of the pricing rule.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (config('settings.is_self_hosted')) {
            return;
        }

        Schema::create('analytics_workspaces', function (Blueprint $table) {
            $table->id();

            $table->ulid('workspace_id')->unique();
            $table->string('workspace_name')->nullable();
            $table->integer('members_count')->default(0);

            // Who to talk to about the money.
            $table->ulid('billing_user_id')->nullable();
            $table->string('billing_user_email')->nullable();
            $table->string('billing_user_name')->nullable();

            // What is used.
            $table->integer('displays_count')->default(0);
            $table->integer('boards_count')->default(0);
            $table->integer('rooms_count')->default(0);
            $table->integer('units')->default(0);

            // How it is paid for.
            $table->boolean('is_unlimited')->default(false);
            $table->boolean('is_manually_billed')->default(false);
            $table->decimal('manual_billing_unit_price', 10, 2)->nullable();
            $table->string('subscription_status')->default('none');
            $table->string('billing_interval')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('subscription_ends_at')->nullable();
            $table->timestamp('subscription_renews_at')->nullable();
            $table->string('lemon_squeezy_id')->nullable();
            $table->decimal('mrr_current', 10, 2)->default(0);
            $table->decimal('mrr_expected', 10, 2)->default(0);

            // Whether anyone is still using it.
            $table->timestamp('workspace_created_at')->nullable();
            $table->timestamp('last_member_activity_at')->nullable();
            $table->timestamp('last_device_activity_at')->nullable();
            $table->timestamp('refreshed_at')->nullable();

            $table->timestamps();

            // The two questions the admin dashboard asks: who is active, and who pays.
            $table->index('last_device_activity_at');
            $table->index('subscription_status');
        });
    }

    public function down(): void
    {
        if (config('settings.is_self_hosted')) {
            return;
        }

        Schema::dropIfExists('analytics_workspaces');
    }
};
