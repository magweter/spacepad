<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An alert can also open as a popup, once for every user: who closed it is kept per user.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('portal_alerts', function (Blueprint $table) {
            $table->boolean('popup')->default(false)->after('provider');
        });

        Schema::create('portal_alert_dismissals', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('portal_alert_id')->constrained('portal_alerts')->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['portal_alert_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_alert_dismissals');
        Schema::table('portal_alerts', function (Blueprint $table) {
            $table->dropColumn('popup');
        });
    }
};
