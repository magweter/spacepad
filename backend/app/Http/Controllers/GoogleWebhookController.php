<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use App\Services\GoogleService;
use App\Models\EventSubscription;

class GoogleWebhookController extends Controller
{
    public function __construct(protected GoogleService $googleService)
    {
    }

    /**
     * Handle incoming notifications from Google Calendar API.
     *
     * @param Request $request
     * @return Response
     */
    public function handleNotification(Request $request): Response
    {
        $subscriptionId = $request->header('X-Goog-Channel-ID');
        logger()->info("Received Google webhook for channel $subscriptionId");

        $newSyncTimestamp = now();

        // Find the corresponding subscription in the database
        $subscription = EventSubscription::with('display')
            ->where('subscription_id', $subscriptionId)
            ->first();

        if ($subscription) {
            // Clear events cache for display
            cache()->forget($subscription->display->getEventsCacheKey());

            // Set new point to sync from
            $subscription->display->update([
                'last_event_at' => $newSyncTimestamp,
            ]);
        }

        if (! $subscription) {
            logger()->warning('Subscription not found', ['subscriptionId' => $subscriptionId]);
        }

        return response('Notification processed', 200);
    }
}
