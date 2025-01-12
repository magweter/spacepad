<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('event_subscriptions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('subscription_id')->unique();  // Unique ID of the subscription from Microsoft Graph
            $table->string('resource');                   // The resource the subscription is for (e.g., 'me/events')
            $table->timestamp('expiration')->nullable();  // Expiration time of the subscription
            $table->string('notification_url');           // URL where the notifications will be sent
            $table->foreignUlid('display_id')->constrained()->onDelete('cascade');
            $table->foreignUlid('outlook_account_id')->constrained()->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_subscriptions');
    }
};
