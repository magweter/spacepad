<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instances', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('instance_id')->unique();
            $table->string('license_key')->nullable();
            $table->integer('num_displays')->default(0);
            $table->string('email_domain')->nullable();
            $table->string('calendar_provider')->nullable();
            $table->string('version')->nullable();
            $table->timestamp('last_heartbeat_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->boolean('is_telemetry_enabled')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instances');
    }
}; 