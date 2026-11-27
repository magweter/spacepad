<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Why a workspace cancelled its subscription. One row per cancellation, so a workspace that
 * comes back and cancels again keeps both answers.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_cancellations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('lemon_squeezy_subscription_id')->nullable()->index();
            // Null while a cancellation made outside the app still waits for its answer.
            $table->string('reason', 32)->nullable();
            $table->text('details')->nullable();
            // in_app: through the workspace page. portal: elsewhere, picked up from the webhook.
            $table->string('source', 16);
            $table->timestamp('answered_at')->nullable();
            $table->timestamp('dismissed_at')->nullable();
            $table->timestamps();
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_cancellations');

    }
};
