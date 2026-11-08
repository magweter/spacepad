<?php

namespace App\Console\Commands;

use App\Enums\DisplayStatus;
use App\Enums\EventSource;
use App\Enums\EventStatus;
use App\Enums\UsageType;
use App\Enums\UserStatus;
use App\Models\Calendar;
use App\Models\Device;
use App\Models\Display;
use App\Models\Event;
use App\Models\User;
use App\Support\MagicLoginLink;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * A ready-to-test setup for local development: an account, a display with meetings around the
 * current time, and a tablet already paired to it.
 *
 * Testing a change on the tablet used to take an e-mail login, a connect code, picking a display
 * and booking meetings in a real calendar. This prints a portal login link and a `flutter run`
 * command that opens the app straight on the dashboard. Running it again reuses everything and
 * moves the meetings along with the clock, so a stale setup is one command away from fresh.
 *
 * Never in production: it mints a device token and a login link for a known e-mail address.
 */
class SeedDemo extends Command
{
    public const EMAIL = 'demo@spacepad.test';

    public const DEVICE_UID = 'spacepad-demo-tablet';

    public const CALENDAR_ID = 'spacepad-demo-calendar';

    protected $signature = 'spacepad:demo {--url= : The backend URL the tablet talks to (default: APP_URL)}';

    protected $description = 'Set up a demo account with a paired display for local testing';

    public function handle(): int
    {
        if (app()->environment('production')) {
            $this->error('The demo setup is for local development and does not run in production.');

            return self::FAILURE;
        }

        [$user, $display, $device] = DB::transaction(function () {
            $user = User::firstOrCreate(['email' => self::EMAIL], [
                'name' => 'Demo',
                'password' => Str::random(40),
                'email_verified_at' => now(),
                'status' => UserStatus::ACTIVE,
                'usage_type' => UsageType::PERSONAL,
                'terms_accepted_at' => now(),
            ]);

            // Pro either way: the hosted service reads the workspace flag, a self-hosted instance
            // without a licence treats a personal account as exempt.
            $workspace = $user->primaryWorkspace();
            $workspace->update(['is_unlimited' => true]);

            // A calendar without a provider account: the display shows only the meetings stored
            // below, and never reaches out to Google, Microsoft or a CalDAV server.
            $calendar = Calendar::firstOrCreate(['calendar_id' => self::CALENDAR_ID], [
                'user_id' => $user->id,
                'workspace_id' => $workspace->id,
                'name' => 'Demo calendar',
            ]);

            $display = Display::firstOrCreate(['calendar_id' => $calendar->id], [
                'user_id' => $user->id,
                'workspace_id' => $workspace->id,
                'name' => 'Boardroom Lighthouse',
                'display_name' => 'Boardroom Lighthouse',
                'status' => DisplayStatus::ACTIVE,
            ]);

            $device = Device::updateOrCreate(['user_id' => $user->id, 'uid' => self::DEVICE_UID], [
                'workspace_id' => $workspace->id,
                'display_id' => $display->id,
                'name' => 'Demo tablet',
            ]);

            $this->seedMeetings($user, $display);

            return [$user, $display, $device];
        });

        // One token at a time: the previous run's token stops working, so only the latest
        // printed command can open the app.
        $device->tokens()->delete();
        $token = $device->createToken('device-token')->plainTextToken;

        $url = rtrim($this->option('url') ?: config('app.url'), '/');

        $this->info('Demo ready: "'.$display->name.'" with a paired tablet and meetings around now.');
        $this->newLine();
        $this->line('Portal login (valid '.MagicLoginLink::LIFETIME_MINUTES.' minutes, works once):');
        $this->line('  '.MagicLoginLink::create($user));
        $this->newLine();
        $this->line('Tablet, from app/:');
        $this->line('  '.self::flutterCommand($url, $token, $display->id));

        return self::SUCCESS;
    }

    /**
     * The `flutter run` line, quoted for a shell: a Sanctum token reads `id|secret`, and an
     * unquoted `|` turns the rest of the line into a pipe.
     */
    public static function flutterCommand(string $url, string $token, string $displayId): string
    {
        return 'fvm flutter run'
            .' --dart-define='.escapeshellarg('DEMO_API_URL='.$url)
            .' --dart-define='.escapeshellarg('DEMO_TOKEN='.$token)
            .' --dart-define='.escapeshellarg('DEMO_DISPLAY_ID='.$displayId);
    }

    /**
     * Replace the display's meetings with a set placed around the current time: one running now
     * with a subject long enough to wrap, one starting soon, a short one, and one earlier today.
     */
    private function seedMeetings(User $user, Display $display): void
    {
        Event::where('display_id', $display->id)->delete();

        // Whole five minutes, the way people book.
        $now = Carbon::now()->startOfMinute();
        $now->minute($now->minute - $now->minute % 5);

        // Stored meetings carry no organizer (that column only exists on calendar-provider
        // events), so the organizer chip stays empty on the demo display.
        $meetings = [
            [-180, -120, 'Morning stand-up'],
            [-25, 35, "Internal review 'Automatically processing incoming work orders' followed by the planning for next quarter and the open questions from last week"],
            [45, 75, 'Quarterly planning'],
            [90, 105, 'Quick sync'],
            [120, 180, 'Design review with the product team'],
        ];

        foreach ($meetings as [$from, $to, $summary]) {
            Event::create([
                'display_id' => $display->id,
                'user_id' => $user->id,
                'status' => EventStatus::CONFIRMED,
                'source' => EventSource::CUSTOM,
                'start' => $now->copy()->addMinutes($from),
                'end' => $now->copy()->addMinutes($to),
                'summary' => $summary,
                'timezone' => config('app.timezone'),
            ]);
        }

        Cache::forget($display->getEventsCacheKey());
        Cache::forget($display->getEventsCacheKey().':fallback');
    }
}
