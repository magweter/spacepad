<?php

namespace App\Providers;

use App\Models\Event as EventModel;
use App\Models\PersonalAccessToken;
use App\Observers\EventObserver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;
use LemonSqueezy\Laravel\LemonSqueezy;
use SocialiteProviders\Manager\SocialiteWasCalled;
use SocialiteProviders\Microsoft\Provider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Don't ignore migrations in test environment - tests need the tables
        if (config('settings.is_self_hosted') && ! app()->environment('testing')) {
            LemonSqueezy::ignoreMigrations();
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);

        RateLimiter::for('public_tokens', function (Request $request) {
            return Limit::perMinute(60)->by($request->ip());
        });

        // Sending invitations is a mail-triggering action, so bound it per user.
        RateLimiter::for('workspace_invites', function (Request $request) {
            return Limit::perHour(20)->by($request->user()?->id ?: $request->ip());
        });

        // The invitation accept routes are public and carry a guessable-looking token.
        RateLimiter::for('invitations', function (Request $request) {
            return Limit::perMinute(20)->by($request->ip());
        });

        EventModel::observe(EventObserver::class);

        Event::listen(DiagnosingHealth::class, function () {
            DB::connection()->getPdo();
            Cache::put('health:probe', true, 10);
        });

        Event::listen(function (SocialiteWasCalled $event) {
            $event->extendSocialite('microsoft', Provider::class);
        });
    }
}
