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
        // Handle webhook validation request (Microsoft Graph requires this)
        if ($request->has('validationToken')) {
            return response($request->validationToken, 200)
                ->header('Content-Type', 'text/plain');
        }

        logger()->info('Received Outlook webhook', [
            'ip' => $request->ip(),
            'notification_count' => count($request->input('value', [])),
        ]);

        $newSyncTimestamp = now();
        $notifications = $request->input('value', []);
        
        // Security: Limit number of notifications per request to prevent DoS
        if (count($notifications) > 100) {
            logger()->warning('Outlook webhook received too many notifications', [
                'count' => count($notifications),
                'ip' => $request->ip(),
            ]);
            return response('Too many notifications', 400);
        }

        foreach ($notifications as $notification) {
            $subscriptionId = Arr::get($notification, 'subscriptionId');
            $resource = Arr::get($notification, 'resource');
            $resourceId = Arr::get($notification, 'resourceData.id');

            // Check for required fields
            if (!$resource || !$resourceId) {
                logger()->warning('Resource or ResourceData was missing from request body', [
                    'subscriptionId' => $subscriptionId,
                ]);
                continue;
            }

            // Security: Only process if subscription exists (prevents cache clearing attacks)
            $subscription = EventSubscription::with('display')
                ->where('subscription_id', $subscriptionId)
                ->first();

            if (!$subscription) {
                logger()->warning('Outlook webhook received for unknown subscription', [
                    'subscriptionId' => $subscriptionId,
                    'ip' => $request->ip(),
                ]);
                // Continue to next notification (don't reveal which subscriptions exist)
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
