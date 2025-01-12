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
        Schema::create('outlook_accounts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained()->onDelete('cascade'); // Link to users table
            $table->string('outlook_id')->unique(); // Unique ID from Microsoft (Outlook)
            $table->string('email')->unique(); // The user's Outlook email
            $table->string('name')->nullable(); // Optional: The user's display name
            $table->text('avatar')->nullable(); // Optional: The user's avatar image
            $table->text('token'); // OAuth access token
            $table->text('refresh_token')->nullable(); // Optional: OAuth refresh token
            $table->timestamp('token_expires_at')->nullable(); // Expiry time for the token
            $table->timestamps(); // Laravel default: created_at and updated_at
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('outlook_accounts');
    }
};
