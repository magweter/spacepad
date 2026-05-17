<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Remove external events that were synced from Google/Outlook/CalDAV.
        // These are no longer stored in the database; they are fetched live from the
        // calendar API on each request and cached temporarily in Redis.
        // Tablet bookings (calendar_id IS NOT NULL) are kept because they track
        // events created from the display and are needed for the cancel flow.
        DB::table('events')
            ->whereIn('source', ['google', 'outlook', 'caldav'])
            ->whereNull('calendar_id')
            ->delete();
    }

    public function down(): void
    {
        // Intentionally empty — deleted rows cannot be recovered here.
    }
};
