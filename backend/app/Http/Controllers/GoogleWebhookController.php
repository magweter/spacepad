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
        logger()->info('Received Google webhook', $request->toArray());

        // Handle webhook validation request
        if ($request->has('challenge')) {
            return response($request->challenge, 200)
                ->header('Content-Type', 'text/plain');
        }

        $newSyncTimestamp = now();

        $notifications = $request->input('notifications', []);
        foreach ($notifications as $notification) {
            $subscriptionId = Arr::get($notification, 'subscriptionId');
            $resourceId = Arr::get($notification, 'resourceId');

            // Check for resource id
            if (!$resourceId) {
                logger()->warning('ResourceId was missing from request body');
                continue;
            }

            // Find the corresponding subscription in the database
            $subscription = EventSubscription::with('display')
                ->where('subscription_id', $subscriptionId)
                ->first();

            if (!$subscription) {
                logger()->warning('Subscription not found', ['subscriptionId' => $subscriptionId]);
                continue;
            }

            // Clear events cache for display
            cache()->forget($subscription->display->getEventsCacheKey());

            // Set new point to sync from
            $subscription->display->update([
                'last_event_at' => $newSyncTimestamp,
            ]);
        }

        return response('Notification processed', 200);
    }
} 