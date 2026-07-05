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

        Schema::table('analytics_users', function (Blueprint $table) {
            $table->ulid('workspace_id')->nullable()->after('user_id');
            $table->string('workspace_name')->nullable()->after('workspace_id');
        });
    }

    public function down(): void
    {
        if (config('settings.is_self_hosted')) {
            return;
        }

        Schema::table('analytics_users', function (Blueprint $table) {
            $table->dropColumn(['workspace_id', 'workspace_name']);
        });
    }
};
