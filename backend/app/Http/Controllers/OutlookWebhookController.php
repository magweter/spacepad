<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use App\Services\OutlookService;
use App\Models\EventSubscription;

class OutlookWebhookController extends Controller
{
    public function __construct(protected OutlookService $outlookService)
    {
    }

    /**
     * Handle incoming notifications from Microsoft Graph.
     *
     * @param Request $request
     * @return Response
     * @throws \Exception
     */
    public function handleNotification(Request $request): Response
    {
        logger()->info('Received webhook', $request->toArray());

        // Handle webhook validation request
        if ($request->has('validationToken')) {
            return response($request->validationToken, 200)
                ->header('Content-Type', 'text/plain');
        }

        $newSyncTimestamp = now();

        $notifications = $request->input('value', []);
        foreach ($notifications as $notification) {
            $subscriptionId = Arr::get($notification, 'subscriptionId');
            $resource = Arr::get($notification, 'resource');
            $resourceId = Arr::get($notification, 'resourceData.id');

            // Check for resource id
            if (!$resource || !$resourceId) {
                logger()->warning('Resource or ResourceData was missing from request body');
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
            $subscription->display->updateLastEventAt($newSyncTimestamp);
        }

        return response('Notification processed', 200);
    }
}
