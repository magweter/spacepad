<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Users who accepted Terms have been subject to data processing since signup.
        // Backfill dpa_accepted_at from terms_accepted_at to grandfather existing users.
        DB::table('users')
            ->whereNotNull('terms_accepted_at')
            ->whereNull('dpa_accepted_at')
            ->update(['dpa_accepted_at' => DB::raw('terms_accepted_at')]);
    }

    public function down(): void
    {
        //
    }
};
