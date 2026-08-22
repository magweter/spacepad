<?php

namespace App\Services;

use App\Models\Workspace;
use Illuminate\Support\Str;
use Spatie\GoogleTagManager\GoogleTagManagerFacade as GoogleTagManager;

/**
 * Decides where Google Tag Manager loads, and reports the milestones on the way to a paid
 * subscription.
 *
 * GTM used to load on every page that extends the blank layout, which made the numbers
 * unusable: a board hangs on a wall and reloads all day, so one tv produced more pageviews
 * and sessions than every real visitor combined. Analytics is an acquisition tool here, not
 * a product-usage tool, so it now loads on the funnel from landing to conversion and stays
 * out of everyday use of the app.
 *
 * Milestones between signup and payment are reported as events instead of pageviews. They
 * happen on pages that are not tracked, so they are pushed onto the data layer and the page
 * that renders them turns the scripts on for that one request.
 */
final class FunnelTracking
{
    /**
     * Route name patterns that make up the funnel from first visit to conversion.
     */
    private const FUNNEL_ROUTES = [
        'login',
        'login.store',
        'register',
        'register.*',
        'invitations.*',
        'onboarding',
        'onboarding.*',
        'billing.*',
    ];

    /**
     * Never tracked, whatever else is true. Boards and displays are unattended screens that
     * reload on a timer, and admin pages are us looking at customer data rather than a
     * visitor moving through the funnel.
     */
    private const UNTRACKED_ROUTES = [
        'boards.*',
        'displays.*',
        'profiles.images',
        'admin.*',
    ];

    /**
     * Whether the GTM snippets belong on the page currently being rendered.
     */
    public static function shouldRenderScripts(): bool
    {
        if (! config('googletagmanager.enabled') || blank(config('googletagmanager.id'))) {
            return false;
        }

        $route = request()->route()?->getName();

        if ($route !== null && Str::is(self::UNTRACKED_ROUTES, $route)) {
            return false;
        }

        // A milestone fired on a POST arrives here after a redirect, on a page that is not
        // itself part of the funnel. Loading GTM for that one request is the whole point of
        // the event, so it wins over the route list.
        if (self::hasQueuedEvents()) {
            return true;
        }

        return $route !== null && Str::is(self::FUNNEL_ROUTES, $route);
    }

    /**
     * The first calendar account in a workspace: the step where the product starts working.
     *
     * Only the first one counts, and only a genuinely new account — reconnecting the single
     * account a workspace already has is not a funnel step.
     */
    public static function calendarConnected(?Workspace $workspace, bool $isNewAccount): void
    {
        if (! $workspace || ! $isNewAccount || self::calendarAccountCount($workspace) > 1) {
            return;
        }

        GoogleTagManager::flashPush(['event' => 'calendar_connected']);
    }

    /**
     * The first display in a workspace.
     */
    public static function displayCreated(?Workspace $workspace): void
    {
        if (! $workspace || $workspace->displays()->count() > 1) {
            return;
        }

        GoogleTagManager::flashPush(['event' => 'display_created']);
    }

    /**
     * The first tablet paired: activation, and the last step before anyone pays.
     *
     * Pairing happens on the tablet against the API, where there is no browser session to
     * push to, so this is picked up the next time the dashboard is opened. "First" is read
     * from the data rather than stored: one device in the workspace, paired in the last hour.
     * A session key stops it repeating while the person keeps refreshing the dashboard.
     */
    public static function devicePaired(?Workspace $workspace): void
    {
        if (! $workspace) {
            return;
        }

        $sessionKey = "funnel.device_paired.{$workspace->id}";

        if (session()->has($sessionKey)) {
            return;
        }

        $devices = $workspace->devices()->get(['id', 'created_at']);

        if ($devices->count() !== 1 || $devices->first()->created_at->lt(now()->subHour())) {
            return;
        }

        session()->put($sessionKey, true);

        // push() rather than flashPush(): the dashboard is rendering right now.
        GoogleTagManager::push(['event' => 'device_paired']);
    }

    /**
     * Whether anything is waiting on the data layer for this request.
     */
    private static function hasQueuedEvents(): bool
    {
        return GoogleTagManager::getPushData()->isNotEmpty()
            || GoogleTagManager::getDataLayer()->toArray() !== [];
    }

    private static function calendarAccountCount(Workspace $workspace): int
    {
        return $workspace->googleAccounts()->count()
            + $workspace->outlookAccounts()->count()
            + $workspace->caldavAccounts()->count();
    }
}
