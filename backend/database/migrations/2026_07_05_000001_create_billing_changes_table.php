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

        Schema::create('billing_changes', function (Blueprint $table) {
            $table->id();
            $table->ulid('user_id');
            $table->string('email');
            $table->string('name');
            $table->integer('previous_displays_count');
            $table->integer('new_displays_count');
            $table->integer('previous_boards_count');
            $table->integer('new_boards_count');
            $table->integer('previous_license_count');  // billable usage = displays + boards×2
            $table->integer('new_license_count');
            $table->integer('license_delta');            // new - previous (signed)
            $table->decimal('previous_mrr', 10, 2)->default(0);
            $table->decimal('new_mrr', 10, 2)->default(0);
            $table->decimal('mrr_delta', 10, 2)->default(0);  // new - previous (signed)
            $table->string('change_type');               // increase | decrease
            $table->string('subscription_status')->nullable();
            $table->timestamp('detected_at');
            $table->timestamps();

            // Supports the admin user detail lookup: where('user_id')->orderByDesc('detected_at').
            $table->index(['user_id', 'detected_at']);
        });
    }

    public function down(): void
    {
        if (config('settings.is_self_hosted')) {
            return;
        }

        Schema::dropIfExists('billing_changes');
    }
};
