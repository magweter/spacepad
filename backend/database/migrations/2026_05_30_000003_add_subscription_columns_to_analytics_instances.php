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

        Schema::table('analytics_instances', function (Blueprint $table) {
            $table->string('lemon_squeezy_id')->nullable()->after('instance_id');
            $table->string('subscription_status')->nullable()->after('is_paid');
            $table->string('billing_interval')->nullable()->after('subscription_status');
        });
    }

    public function down(): void
    {
        if (config('settings.is_self_hosted')) {
            return;
        }

        Schema::table('analytics_instances', function (Blueprint $table) {
            $table->dropColumn(['lemon_squeezy_id', 'subscription_status', 'billing_interval']);
        });
    }
};
