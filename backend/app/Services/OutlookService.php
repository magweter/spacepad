<?php

namespace App\Services;

use App\Enums\Provider;
use App\Models\EventSubscription;
use App\Models\OutlookAccount;
use App\Models\Synchronization;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Microsoft\Graph\Core\Authentication\TokenCredentialAuthProvider;
use Microsoft\Kiota\Authentication\OAuth\AccessTokenProvider;
use Microsoft\Graph\Model;

class OutlookService
{
    const OAUTH_SCOPES = 'openid email profile offline_access User.Read Calendars.Read.Shared Place.Read.All';
    protected mixed $clientId;
    protected mixed $clientSecret;
    protected mixed $redirectUri;
    protected mixed $tenantId;

    public function __construct()
    {
        $this->clientId = config('services.azure_ad.client_id');
        $this->clientSecret = config('services.azure_ad.client_secret');
        $this->redirectUri = config('services.azure_ad.redirect');
        $this->tenantId = config('services.azure_ad.tenant_id');
    }

    /**
     * Get the access token for Google Calendar API
     * @throws \Exception
     */
    private function ensureAuthenticated(&$outlookAccount): void
    {
        if (now()->lte($outlookAccount->token_expires_at)) {
            return;
        }

        // Set the access token for API requests
        $this->refreshToken($outlookAccount);
    }

    /**
     * Generate Outlook OAuth URL for authentication.
     *
     * @return string
     */
    public function getAuthUrl(): string
    {
        $oauthEndpoint = "https://login.microsoftonline.com/{$this->tenantId}/oauth2/v2.0/authorize";

        $params = [
            'client_id' => $this->clientId,
            'response_type' => 'code',
            'redirect_uri' => $this->redirectUri,
            'response_mode' => 'query',
            'scope' => self::OAUTH_SCOPES,
            'state' => csrf_token(),
        ];

        return $oauthEndpoint . '?' . http_build_query($params);
    }

    /**
     * Handle Outlook OAuth callback and store tokens in the database.
     *
     * @param string $authCode
     * @return void
     * @throws \Exception
     */
    public function authenticateOutlookAccount(string $authCode): void
    {
        $oauthTokenEndpoint = "https://login.microsoftonline.com/{$this->tenantId}/oauth2/v2.0/token";

        // Exchange authorization code for tokens
        $response = \Http::asForm()->post($oauthTokenEndpoint, [
            'client_id' => $this->clientId,
            'scope' => self::OAUTH_SCOPES,
            'code' => $authCode,
            'redirect_uri' => $this->redirectUri,
            'grant_type' => 'authorization_code',
            'client_secret' => $this->clientSecret,
        ]);

        $tokenData = $response->json();
        if (isset($tokenData['error'])) {
            throw new \Exception('Error authenticating with Outlook: ' . $tokenData['error_description']);
        }

        // Get the current user information
        $response = Http::acceptJson()->withHeaders([
            'Authorization' => 'Bearer ' . $tokenData['access_token'],
        ])->get('https://graph.microsoft.com/v1.0/me');

        $user = $response->json();

        // Save the Outlook account and tokens
        OutlookAccount::updateOrCreate(
            [
                'user_id' => Auth::id(),  // Associate with the current user
                'outlook_id' => $user['id'],
            ],
            [
                'email' => $user['mail'],
                'name' => $user['displayName'],
                'token' => $tokenData['access_token'],
                'refresh_token' => $tokenData['refresh_token'] ?? null,
                'token_expires_at' => now()->addSeconds($tokenData['expires_in']),
            ]
        );
    }

    /**
     * Refresh Outlook access token.
     *
     * @param OutlookAccount $outlookAccount
     * @return void
     * @throws \Exception
     */
    protected function refreshToken(OutlookAccount &$outlookAccount): void
    {
        $oauthTokenEndpoint = "https://login.microsoftonline.com/{$this->tenantId}/oauth2/v2.0/token";

        $response = \Http::asForm()->post($oauthTokenEndpoint, [
            'client_id' => $this->clientId,
            'scope' => self::OAUTH_SCOPES,
            'refresh_token' => $outlookAccount->refresh_token,
            'grant_type' => 'refresh_token',
            'client_secret' => $this->clientSecret,
        ]);

        $tokenData = $response->json();

        if (isset($tokenData['error'])) {
            throw new \Exception('Error refreshing Outlook token: ' . $tokenData['error_description']);
        }

        $outlookAccount->update([
            'token' => $tokenData['access_token'],
            'token_expires_at' => now()->addSeconds($tokenData['expires_in'])->subSeconds(5),
            'refresh_token' => $tokenData['refresh_token'] ?? $outlookAccount->refresh_token,
        ]);
    }

    /**
     * Fetch calendar events from Outlook account.
     *
     * @param OutlookAccount $outlookAccount
     * @param string $calendarId
     * @param Carbon $startDateTime
     * @param Carbon $endDateTime
     * @return mixed
     * @throws \Exception
     */
    public function fetchEvents(
        OutlookAccount $outlookAccount,
        string $emailAddress,
        Carbon $startDateTime,
        Carbon $endDateTime,
    ): array
    {
        $this->ensureAuthenticated($outlookAccount);

        $params = [
            'startDateTime' => $startDateTime->toIso8601String(),
            'endDateTime' => $endDateTime->toIso8601String(),
            '$select' => 'id,lastModifiedDateTime,subject,body,bodyPreview,isAllDay,location,start,end',
            '$orderby' => 'createdDateTime',
            '$top' => 100
        ];

        $response = Http::withToken($outlookAccount->token)
            ->get("https://graph.microsoft.com/v1.0/users/$emailAddress/calendarview", $params);

        return Arr::get($response->json(), 'value') ?? [];
    }

    /**
     * @throws \Exception
     */
    public function fetchSerieInstances(
        OutlookAccount $outlookAccount,
        string $seriesMasterId,
        Carbon $startDateTime,
        Carbon $endDateTime,
    ): array
    {
        $this->ensureAuthenticated($outlookAccount);

        $params = [
            'startDateTime' => $startDateTime->toIso8601String(),
            'endDateTime' => $endDateTime->toIso8601String()
        ];

        $response = Http::withToken($outlookAccount->token)
            ->get("https://graph.microsoft.com/v1.0/me/events/$seriesMasterId/instances", $params);

        return Arr::get($response->json(), 'value') ?? [];
    }

    /**
     * Fetch calendar events from Outlook account.
     *
     * @param OutlookAccount $outlookAccount
     * @param string $eventId
     * @return mixed
     * @throws \Exception
     */
    public function fetchEvent(
        OutlookAccount $outlookAccount,
        string $eventId,
    ): mixed
    {
        $this->ensureAuthenticated($outlookAccount);

        $response = Http::withToken($outlookAccount->token)
            ->get("https://graph.microsoft.com/v1.0/me/events/$eventId");

        return Arr::get($response->json(), 'value');
    }

    /**
     * Fetch calendar events from Outlook account.
     *
     * @param OutlookAccount $outlookAccount
     * @param string $resource
     * @return mixed
     * @throws \Exception
     */
    public function fetchResource(
        OutlookAccount $outlookAccount,
        string $resource,
    ): mixed
    {
        $this->ensureAuthenticated($outlookAccount);

        $response = Http::withToken($outlookAccount->token)
            ->get("https://graph.microsoft.com/v1.0/$resource");

        $payload = $response->json();
        if ($response->failed() || ! Arr::has($payload, ['subject'])) {
            logger()->error('Misformed outlook event', [
                'status' => $response->status(),
                'response' => $payload
            ]);
        }

        return $payload;
    }

    /**
     * Fetch calendars from the authenticated user's Outlook account.
     *
     * @param OutlookAccount $outlookAccount
     * @return mixed
     * @throws \Exception
     */
    public function fetchCalendars(OutlookAccount $outlookAccount): mixed
    {
        $this->ensureAuthenticated($outlookAccount);

        // Get the current user information
        $response = Http::acceptJson()->withHeaders([
            'Authorization' => 'Bearer ' . $outlookAccount->token,
        ])->get('https://graph.microsoft.com/v1.0/me/calendars');

        return \Arr::get($response->json(), 'value');
    }

    /**
     * Fetch a calendar from the authenticated user's Outlook account.
     *
     * @param OutlookAccount $outlookAccount
     * @return mixed
     * @throws \Exception
     */
    public function fetchCalendarByEmail(OutlookAccount $outlookAccount, string $emailAddress): mixed
    {
        $this->ensureAuthenticated($outlookAccount);

        // Get the current user information
        $response = Http::acceptJson()->withHeaders([
            'Authorization' => 'Bearer ' . $outlookAccount->token,
        ])->get("https://graph.microsoft.com/v1.0/users/$emailAddress/calendar");

        return $response->json();
    }

    /**
     * Fetch rooms from the authenticated user's Outlook account.
     *
     * @param OutlookAccount $outlookAccount
     * @return mixed
     * @throws \Exception
     */
    public function fetchRooms(OutlookAccount $outlookAccount): mixed
    {
        $this->ensureAuthenticated($outlookAccount);

        // Get the current user information
        $response = Http::acceptJson()->withHeaders([
            'Authorization' => 'Bearer ' . $outlookAccount->token,
        ])->get('https://graph.microsoft.com/v1.0/places/microsoft.graph.room');

        return \Arr::get($response->json(), 'value');
    }

    /**
     * Create an event subscription for Outlook calendar events.
     *
     * @param OutlookAccount $outlookAccount
     * @param Synchronization $synchronization
     * @param string $calendarId
     * @return EventSubscription|null
     * @throws \Exception
     */
    public function createEventSubscription(
        OutlookAccount $outlookAccount,
        Synchronization $synchronization,
        string $calendarId
    ): ?EventSubscription
    {
        $this->ensureAuthenticated($outlookAccount);

        $data = [
            'resource' => "/me/calendars/{$calendarId}/events",
            'changeType' => 'created,updated,deleted',
            'notificationUrl' => config('services.azure_ad.webhook_url'),
            'expirationDateTime' => now()->addHours(3)->toISOString(),
            'includeResourceData' => "false",
        ];

        logger()->info('Creating subscription', [
            'data' => $data
        ]);

        // Create a subscription with Microsoft Graph
        $response = Http::withToken($outlookAccount->token)
            ->post("https://graph.microsoft.com/v1.0/subscriptions", $data);

        $responseBody = $response->json();
        if (
            $response->failed() ||
            ! Arr::has($responseBody, ['id', 'resource', 'expirationDateTime', 'notificationUrl'])
        ) {
            logger()->error('Creating outlook subscription failed', [
                'statuscode' => $response->status(),
                'response' => $responseBody
            ]);
            return null;
        }

        // Create the subscription record in the database
        $eventSubscription = EventSubscription::create([
            'subscription_id' => $responseBody['id'],
            'resource' => $responseBody['resource'],
            'expiration' => $responseBody['expirationDateTime'],
            'notification_url' => $data['notificationUrl'],
            'synchronization_id' => $synchronization->id,
            'outlook_account_id' => $outlookAccount->id,
            'provider' => Provider::OUTLOOK,
        ]);

        // Log the creation for debugging
        logger()->info('Outlook subscription created', ['subscription' => $responseBody]);

        return $eventSubscription;
    }

    /**
     * Delete an event subscription in Outlook.
     *
     * @param OutlookAccount $outlookAccount
     * @param EventSubscription $eventSubscription
     * @param bool $useApi
     * @return void
     * @throws \Exception
     */
    public function deleteEventSubscription(
        OutlookAccount $outlookAccount,
        EventSubscription $eventSubscription,
        bool $useApi = true
    ): void
    {
        $this->ensureAuthenticated($outlookAccount);

        // Delete the subscription on Microsoft Graph
        if ($useApi) {
            Http::withToken($outlookAccount->token)
                ->delete("https://graph.microsoft.com/v1.0/subscriptions/{$eventSubscription->subscription_id}");
        }

        // Delete the subscription record from the database
        $eventSubscription->delete();

        // Log the deletion for debugging
        logger()->info('Outlook subscription deleted', ['subscriptionId' => $eventSubscription->id]);
    }
}
