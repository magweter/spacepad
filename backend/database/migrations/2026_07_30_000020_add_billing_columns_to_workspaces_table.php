<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Billing moves from the user to the workspace.
 *
 * Usage was already computed per workspace (displays x1 + boards x2) while the money hung
 * off a user, so "who pays for what" was ambiguous the moment a workspace had more than
 * one member. These columns are the workspace-side home for that state.
 *
 * Additive only: nothing reads them until Workspace::hasPro() is switched over, so this is
 * safe to deploy on its own.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->boolean('is_unlimited')->default(false)->after('name');
            $table->boolean('is_manually_billed')->default(false)->after('is_unlimited');
            $table->decimal('manual_billing_unit_price', 10, 2)->nullable()->after('is_manually_billed');

            // Who carries billing for this workspace. Explicit rather than derived from
            // "earliest owner membership", which silently picks the wrong person the first
            // time an owner is removed and re-added.
            $table->foreignUlid('billing_owner_user_id')->nullable()->after('manual_billing_unit_price')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->dropConstrainedForeignId('billing_owner_user_id');
            $table->dropColumn(['is_unlimited', 'is_manually_billed', 'manual_billing_unit_price']);
        });
    }
};
