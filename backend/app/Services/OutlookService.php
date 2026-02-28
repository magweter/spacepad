<?php

namespace App\Services;

use App\Enums\AccountStatus;
use App\Enums\PermissionType;
use App\Models\Calendar;
use App\Models\Display;
use App\Models\EventSubscription;
use App\Models\OutlookAccount;
use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

class OutlookService
{
    const OAUTH_SCOPES_READ = 'openid email profile offline_access User.Read Calendars.Read.Shared Place.Read.All';
    const OAUTH_SCOPES_WRITE = 'openid email profile offline_access User.Read Calendars.ReadWrite.Shared Calendars.Read.Shared Place.Read.All';
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
     * @param PermissionType $permissionType 'read' or 'write', or PermissionType enum
     * @return string
     */
    public function getAuthUrl(PermissionType $permissionType = PermissionType::READ): string
    {
        $oauthEndpoint = "https://login.microsoftonline.com/{$this->tenantId}/oauth2/v2.0/authorize";

        $scopes = $permissionType === PermissionType::WRITE ? self::OAUTH_SCOPES_WRITE : self::OAUTH_SCOPES_READ;

        $params = [
            'client_id' => $this->clientId,
            'response_type' => 'code',
            'redirect_uri' => $this->redirectUri,
            'response_mode' => 'query',
            'scope' => $scopes,
            'state' => csrf_token(),
        ];

        return $oauthEndpoint . '?' . http_build_query($params);
    }

    /**
     * Handle Outlook OAuth callback and store tokens in the database.
     *
     * @param string $authCode
     * @param string|PermissionType $permissionType 'read' or 'write', or PermissionType enum
     * @return OutlookAccount
     * @throws \Exception
     */
    public function authenticateOutlookAccount(string $authCode, string|PermissionType $permissionType = PermissionType::READ): OutlookAccount
    {
        $oauthTokenEndpoint = "https://login.microsoftonline.com/{$this->tenantId}/oauth2/v2.0/token";

        // Convert string to enum if needed
        if (is_string($permissionType)) {
            $permissionType = PermissionType::from($permissionType);
        }

        $scopes = $permissionType === PermissionType::WRITE ? self::OAUTH_SCOPES_WRITE : self::OAUTH_SCOPES_READ;

        // Exchange authorization code for tokens
        $response = Http::asForm()->post($oauthTokenEndpoint, [
            'client_id' => $this->clientId,
            'scope' => $scopes,
            'code' => $authCode,
            'redirect_uri' => $this->redirectUri,
            'grant_type' => 'authorization_code',
            'client_secret' => $this->clientSecret,
        ]);

        $tokenData = $response->json();
        if (Arr::exists($tokenData, 'error')) {
            throw new Exception('Error authenticating with Outlook: ' . Arr::get($tokenData, 'error.message'));
        }

        // Get the current user information
        $response = Http::acceptJson()
            ->withToken($tokenData['access_token'])
            ->get('https://graph.microsoft.com/v1.0/me');

        $user = $response->json();

        $tenantId = $this->getTenantId($tokenData['access_token']);

        // Get selected workspace (from session or default to primary)
        $selectedWorkspace = auth()->user()->getSelectedWorkspace();
        $workspaceId = $selectedWorkspace?->id;

        // Save the Outlook account and tokens
        return OutlookAccount::updateOrCreate(
            [
                'user_id' => auth()->id(),
                'outlook_id' => $user['id'],
                'workspace_id' => $workspaceId,
            ],
            [
                'user_id' => auth()->id(),
                'workspace_id' => $workspaceId,
                'email' => $user['mail'] ?? $user['userPrincipalName'],
                'name' => $user['displayName'],
                'tenant_id' => $tenantId,
                'permission_type' => $permissionType->value,
                'token' => $tokenData['access_token'],
                'refresh_token' => $tokenData['refresh_token'] ?? null,
                'token_expires_at' => now()->addSeconds($tokenData['expires_in']),
                'status' => AccountStatus::CONNECTED,
            ]
        );
    }

    public function getTenantId(string $token): ?string
    {
        try {
            $response = Http::withToken($token)
                ->get('https://graph.microsoft.com/v1.0/organization');

            if (!$response->successful()) {
                logger()->error('Failed to fetch Microsoft user info', [
                    'status' => $response->status(),
                    'response' => $response->json(),
                ]);
                return null;
            }

            $data = Arr::get($response->json(), 'value') ?? [];
            return Arr::get($data, '0.id');
        } catch (\Exception $e) {
            report($e);
            return null;
        }
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

        $scopes = $outlookAccount->permission_type === PermissionType::WRITE ? self::OAUTH_SCOPES_WRITE : self::OAUTH_SCOPES_READ;

        $response = Http::asForm()->post($oauthTokenEndpoint, [
            'client_id' => $this->clientId,
            'scope' => $scopes,
            'refresh_token' => $outlookAccount->refresh_token,
            'grant_type' => 'refresh_token',
            'client_secret' => $this->clientSecret,
        ]);

        $tokenData = $response->json();

        if (Arr::exists($tokenData, 'error')) {
            $outlookAccount->update([
                'status' => AccountStatus::ERROR,
            ]);
            throw new Exception('Error refreshing Outlook token: ' . Arr::get($tokenData, 'error.message'));
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
    ): array {
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
    ): array {
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
     * Create an event in Outlook calendar.
     *
     * @param OutlookAccount $outlookAccount
     * @param Calendar $calendar
     * @param string $summary
     * @param Carbon $start
     * @param Carbon $end
     * @return array|null
     * @throws \Exception
     */
    public function createEvent(
        OutlookAccount $outlookAccount,
        Calendar $calendar,
        string $summary,
        Carbon $start,
        Carbon $end
    ): ?array {
        $this->ensureAuthenticated($outlookAccount);

        $eventData = [
            'subject' => $summary,
            'start' => [
                'dateTime' => $start->toIso8601String(),
                'timeZone' => $start->timezone->getName(),
            ],
            'end' => [
                'dateTime' => $end->toIso8601String(),
                'timeZone' => $end->timezone->getName(),
            ],
        ];

        // Determine the endpoint based on whether it's a room or calendar
        if ($calendar->room) {
            // For rooms, use the user's calendar
            $endpoint = "https://graph.microsoft.com/v1.0/users/{$calendar->calendar_id}/calendar/events";
        } elseif ($calendar->is_primary) {
            // For primary calendar, use /me/calendar/events (without calendar ID)
            $endpoint = "https://graph.microsoft.com/v1.0/me/calendar/events";
        } else {
            // For other calendars, use the calendar ID
            $endpoint = "https://graph.microsoft.com/v1.0/me/calendars/{$calendar->calendar_id}/events";
        }

        $response = Http::acceptJson()
            ->withHeaders([
                'Authorization' => 'Bearer ' . $outlookAccount->token,
            ])
            ->post($endpoint, $eventData);

        if (!$response->successful()) {
            throw new Exception('Failed to create Outlook event: ' . $response->body());
        }

        return $response->json();
    }

    /**
     * Delete an event from Outlook calendar.
     *
     * @param OutlookAccount $outlookAccount
     * @param Calendar $calendar
     * @param string $eventId
     * @return void
     * @throws \Exception
     */
    public function deleteEvent(
        OutlookAccount $outlookAccount,
        Calendar $calendar,
        string $eventId
    ): void {
        $this->ensureAuthenticated($outlookAccount);

        // Determine the endpoint based on whether it's a room or calendar
        if ($calendar->room) {
            // For rooms, use the user's calendar
            $endpoint = "https://graph.microsoft.com/v1.0/users/{$calendar->calendar_id}/calendar/events/{$eventId}";
        } elseif ($calendar->is_primary) {
            // For primary calendar, use /me/calendar/events (without calendar ID)
            $endpoint = "https://graph.microsoft.com/v1.0/me/calendar/events/{$eventId}";
        } else {
            // For other calendars, use the calendar ID
            $endpoint = "https://graph.microsoft.com/v1.0/me/calendars/{$calendar->calendar_id}/events/{$eventId}";
        }

        $response = Http::acceptJson()
            ->withHeaders([
                'Authorization' => 'Bearer ' . $outlookAccount->token,
            ])
            ->delete($endpoint);

        if (!$response->successful()) {
            throw new Exception('Failed to delete Outlook event: ' . $response->body());
        }
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
    ): ?EventSubscription {
        // Try the standard path first
        try {
            return $this->createEventSubscription($outlookAccount, $display, "/users/$emailAddress/events");
        } catch (\Exception $e) {
            // If it fails with a resource invalid error, try with /calendar/ path as backup
            if (str_contains($e->getMessage(), 'Resource') && str_contains($e->getMessage(), 'invalid')) {
                logger()->warning('Subscription failed with /events path, trying /calendar/events as backup', [
                    'email' => $emailAddress,
                    'display_id' => $display->id,
                    'error' => $e->getMessage(),
                ]);
                return $this->createEventSubscription($outlookAccount, $display, "/users/$emailAddress/calendar/events");
            }
            // Re-throw if it's not a resource invalid error
            throw $e;
        }
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
    ): ?EventSubscription {
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
    ): ?EventSubscription {
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

        try {
            // Create a subscription with Microsoft Graph
            $response = Http::withToken($outlookAccount->token)
                ->post("https://graph.microsoft.com/v1.0/subscriptions", $data);

            $responseBody = $response->json();
            if (
                $response->failed() ||
                !Arr::has($responseBody, ['id', 'resource', 'expirationDateTime', 'notificationUrl'])
            ) {
                $statusCode = $response->status();
                $isUserError = $statusCode >= 400 && $statusCode < 500;
                
                logger()->error('Creating outlook subscription failed', [
                    'statuscode' => $statusCode,
                    'response' => $responseBody,
                    'is_user_error' => $isUserError,
                ]);
                
                // Throw exception for user errors (4xx) so the command can handle it
                // Return null for server errors (5xx) to avoid marking display as error
                if ($isUserError) {
                    throw new Exception("Failed to create Outlook subscription: HTTP {$statusCode} - " . ($responseBody['error']['message'] ?? $responseBody['message'] ?? 'Unknown error'));
                }
                
                return null;
            }
        } catch (Exception $e) {
            // Re-throw if it's already a user error exception we just created
            if (str_contains($e->getMessage(), 'Failed to create Outlook subscription')) {
                throw $e;
            }
            // For connection errors, timeouts, etc., don't throw - these are transient
            logger()->error('Error creating outlook subscription - connection/timeout error', [
                'error' => $e->getMessage(),
                'exception_type' => get_class($e),
            ]);
            return null;
        }

        // Create the subscription record in the database
        $eventSubscription = EventSubscription::create([
            'subscription_id' => $responseBody['id'],
            'resource' => $responseBody['resource'],
            'expiration' => Carbon::parse($responseBody['expirationDateTime']),
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
    ): void {
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
