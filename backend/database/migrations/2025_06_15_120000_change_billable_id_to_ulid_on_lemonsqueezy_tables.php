<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (config('settings.is_self_hosted')) {
            return;
        }
        
        $connection = Schema::getConnection();
        $driver = $connection->getDriverName();

        if ($driver === 'sqlite') {
            // Customers table
            Schema::table('lemon_squeezy_customers', function (Blueprint $table) {
                $table->dropUnique('lemon_squeezy_customers_billable_id_billable_type_unique');
            });
            Schema::table('lemon_squeezy_customers', function (Blueprint $table) {
                $table->dropColumn('billable_id');
            });
            Schema::table('lemon_squeezy_customers', function (Blueprint $table) {
                $table->ulid('billable_id')->after('id');
            });

            // Subscriptions table
            Schema::table('lemon_squeezy_subscriptions', function (Blueprint $table) {
                $table->dropColumn('billable_id');
            });
            Schema::table('lemon_squeezy_subscriptions', function (Blueprint $table) {
                $table->ulid('billable_id')->after('id');
            });

            // Orders table
            Schema::table('lemon_squeezy_orders', function (Blueprint $table) {
                $table->dropColumn('billable_id');
            });
            Schema::table('lemon_squeezy_orders', function (Blueprint $table) {
                $table->ulid('billable_id')->after('id');
            });
        } else {
            // Customers table
            Schema::table('lemon_squeezy_customers', function (Blueprint $table) {
                $table->ulid('billable_id')->change();
            });
            // Subscriptions table
            Schema::table('lemon_squeezy_subscriptions', function (Blueprint $table) {
                $table->ulid('billable_id')->change();
            });
            // Orders table
            Schema::table('lemon_squeezy_orders', function (Blueprint $table) {
                $table->ulid('billable_id')->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (config('settings.is_self_hosted')) {
            return;
        }
        
        // Customers table
        Schema::table('lemon_squeezy_customers', function (Blueprint $table) {
            $table->unsignedBigInteger('billable_id')->change();
        });

        // Subscriptions table
        Schema::table('lemon_squeezy_subscriptions', function (Blueprint $table) {
            $table->unsignedBigInteger('billable_id')->change();
        });

        // Orders table
        Schema::table('lemon_squeezy_orders', function (Blueprint $table) {
            $table->unsignedBigInteger('billable_id')->change();
        });
    }
}; 