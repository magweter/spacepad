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
            $table->string('instance_id')->unique();
            $table->string('license_key')->nullable();
            $table->boolean('is_self_hosted')->nullable();
            $table->json('users')->nullable();
            $table->json('accounts')->nullable();
            $table->string('version')->nullable();
            $table->timestamp('last_heartbeat_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instances');
    }
};
