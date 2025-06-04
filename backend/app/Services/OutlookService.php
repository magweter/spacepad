<?php

namespace App\Services;

use App\Enums\AccountStatus;
use App\Models\Display;
use App\Models\EventSubscription;
use App\Models\OutlookAccount;
use Exception;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

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
        $response = Http::asForm()->post($oauthTokenEndpoint, [
            'client_id' => $this->clientId,
            'scope' => self::OAUTH_SCOPES,
            'code' => $authCode,
            'redirect_uri' => $this->redirectUri,
            'grant_type' => 'authorization_code',
            'client_secret' => $this->clientSecret,
        ]);

        $tokenData = $response->json();
        if (isset($tokenData['error'])) {
            throw new Exception('Error authenticating with Outlook: ' . $tokenData['error_description']);
        }

        // Get the current user information
        $response = Http::acceptJson()->withHeaders([
            'Authorization' => 'Bearer ' . $tokenData['access_token'],
        ])->get('https://graph.microsoft.com/v1.0/me');

        $user = $response->json();

        // Save the Outlook account and tokens
        OutlookAccount::updateOrCreate(
            [
                'user_id' => auth()->id(),
                'outlook_id' => $user['id'],
            ],
            [
                'user_id' => auth()->id(),
                'email' => $user['mail'],
                'name' => $user['displayName'],
                'token' => $tokenData['access_token'],
                'refresh_token' => $tokenData['refresh_token'] ?? null,
                'token_expires_at' => now()->addSeconds($tokenData['expires_in']),
                'status' => AccountStatus::CONNECTED,
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

        $response = Http::asForm()->post($oauthTokenEndpoint, [
            'client_id' => $this->clientId,
            'scope' => self::OAUTH_SCOPES,
            'refresh_token' => $outlookAccount->refresh_token,
            'grant_type' => 'refresh_token',
            'client_secret' => $this->clientSecret,
        ]);

        $tokenData = $response->json();

        if (isset($tokenData['error'])) {
            $outlookAccount->update([
                'status' => AccountStatus::ERROR,
            ]);
            throw new Exception('Error refreshing Outlook token: ' . $tokenData['error_description']);
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
     * @param string $emailAddress
     * @param Carbon $startDateTime
     * @param Carbon $endDateTime
     * @return mixed
     * @throws \Exception
     */
    public function fetchEventsByUser(
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
     * Fetch calendar events from Outlook account.
     *
     * @param OutlookAccount $outlookAccount
     * @param string $calendarId
     * @param Carbon $startDateTime
     * @param Carbon $endDateTime
     * @return mixed
     * @throws \Exception
     */
    public function fetchEventsByCalendar(
        OutlookAccount $outlookAccount,
        string $calendarId,
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
            ->get("https://graph.microsoft.com/v1.0/me/calendars/$calendarId/calendarview", $params);

        return Arr::get($response->json(), 'value') ?? [];
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

        return Arr::get($response->json(), 'value');
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

        return Arr::get($response->json(), 'value');
    }

    /**
     * Create an event subscription for Outlook calendar events.
     *
     * @param OutlookAccount $outlookAccount
     * @param Display $display
     * @param string $emailAddress
     * @return EventSubscription|null
     * @throws \Exception
     */
    public function createEventSubscriptionByUser(
        OutlookAccount $outlookAccount,
        Display $display,
        string $emailAddress
    ): ?EventSubscription
    {
        return $this->createEventSubscription($outlookAccount, $display, "/users/$emailAddress/events");
    }

    /**
     * Create an event subscription for Outlook calendar events.
     *
     * @param OutlookAccount $outlookAccount
     * @param Display $display
     * @param string $calendarId
     * @return EventSubscription|null
     * @throws \Exception
     */
    public function createEventSubscriptionByCalendar(
        OutlookAccount $outlookAccount,
        Display $display,
        string $calendarId
    ): ?EventSubscription
    {
        return $this->createEventSubscription($outlookAccount, $display, "/me/calendars/$calendarId/events");
    }

    /**
     * Create an event subscription for Outlook calendar events.
     *
     * @param OutlookAccount $outlookAccount
     * @param Display $display
     * @param string $resource
     * @return EventSubscription|null
     * @throws \Exception
     */
    private function createEventSubscription(
        OutlookAccount $outlookAccount,
        Display $display,
        string $resource
    ): ?EventSubscription
    {
        $this->ensureAuthenticated($outlookAccount);

        $data = [
            'resource' => $resource,
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
            'display_id' => $display->id,
            'outlook_account_id' => $outlookAccount->id,
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
        // Delete the subscription on Microsoft Graph
        if ($useApi) {
            $this->ensureAuthenticated($outlookAccount);

            Http::withToken($outlookAccount->token)
                ->delete("https://graph.microsoft.com/v1.0/subscriptions/{$eventSubscription->subscription_id}");
        }

        // Delete the subscription record from the database
        $eventSubscription->delete();

        // Log the deletion for debugging
        logger()->info('Outlook subscription deleted', ['subscriptionId' => $eventSubscription->id]);
    }
}
