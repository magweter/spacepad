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
        
        // Security: Require subscription ID header
        if (empty($subscriptionId)) {
            logger()->warning('Google webhook received without subscription ID', [
                'ip' => $request->ip(),
                'headers' => $request->headers->all(),
            ]);
            return response('Invalid request', 400);
        }

        logger()->info("Received Google webhook for channel $subscriptionId", [
            'ip' => $request->ip(),
        ]);

        // Find the corresponding subscription in the database
        // Security: Only process if subscription exists (prevents cache clearing attacks)
        $subscription = EventSubscription::with('display')
            ->where('subscription_id', $subscriptionId)
            ->first();

        if (!$subscription) {
            logger()->warning('Google webhook received for unknown subscription', [
                'subscriptionId' => $subscriptionId,
                'ip' => $request->ip(),
            ]);
            // Return 200 to prevent subscription enumeration, but don't process
            return response('Notification processed', 200);
        }

        $newSyncTimestamp = now();

        // Clear events cache for display
        cache()->forget($subscription->display->getEventsCacheKey());

        // Set new point to sync from
        $subscription->display->updateLastEventAt($newSyncTimestamp);

        return response('Notification processed', 200);
    }
}
