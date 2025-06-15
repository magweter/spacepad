<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instances', function (Blueprint $table) {
            $table->ulid('id');
            $table->string('instance_key')->unique();
            $table->string('license_key')->nullable();
            $table->boolean('license_valid')->nullable();
            $table->timestamp('license_expires_at')->nullable();
            $table->boolean('is_self_hosted')->nullable();
            $table->integer('displays_count')->nullable();
            $table->integer('rooms_count')->nullable();
            $table->json('users')->nullable();
            $table->string('version')->nullable();
            $table->timestamp('last_validated_at')->nullable();
            $table->timestamp('last_heartbeat_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instances');
    }
};
