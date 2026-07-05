<?php

namespace App\Services;

use App\Enums\AccountStatus;
use App\Enums\OutlookBookingMethod;
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
     *
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
     * @param  PermissionType  $permissionType  'read' or 'write', or PermissionType enum
     */
    public function getAdminConsentUrl(?string $outlookAccountId = null): string
    {
        $params = [
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
        ];

        if ($outlookAccountId) {
            $params['state'] = 'account:'.$outlookAccountId;
        }

        return 'https://login.microsoftonline.com/common/adminconsent?'.http_build_query($params);
    }

    /**
     * Obtain an app-only (client credentials) access token for the given tenant.
     * Used for admin-consent room bookings that write directly to the room mailbox.
     */
    private function getAppOnlyToken(string $tenantId): string
    {
        $response = Http::asForm()->post(
            "https://login.microsoftonline.com/{$tenantId}/oauth2/v2.0/token",
            [
                'grant_type' => 'client_credentials',
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'scope' => 'https://graph.microsoft.com/.default',
            ]
        );

        $data = $response->json();

        if (! $response->successful() || empty($data['access_token'])) {
            throw new Exception('Failed to obtain app-only token: '.($data['error_description'] ?? $response->body()));
        }

        return $data['access_token'];
    }

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

        return $oauthEndpoint.'?'.http_build_query($params);
    }

    /**
     * Handle Outlook OAuth callback and store tokens in the database.
     *
     * @param  string|PermissionType  $permissionType  'read' or 'write', or PermissionType enum
     *
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
            throw new Exception('Error authenticating with Outlook: '.Arr::get($tokenData, 'error.message'));
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

            if (! $response->successful()) {
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
            $errorCode = Arr::get($tokenData, 'error');
            $errorDescription = Arr::get($tokenData, 'error_description', $errorCode);

            // Only permanently mark the account as ERROR for failures that will
            // never recover without user action (revoked consent, invalid credentials).
            // Transient failures (rate limits, server errors) just throw so the
            // next run retries cleanly.
            $permanentErrors = ['invalid_grant', 'invalid_client', 'unauthorized_client', 'consent_required', 'interaction_required'];
            $isPermanent = in_array($errorCode, $permanentErrors, true);

            // Log every refresh failure with the exact error Microsoft returned so
            // permanent account errors can be diagnosed (the customer otherwise has
            // no visibility into *why* an account flips to the error state).
            logger()->error('Outlook token refresh failed', [
                'outlook_account_id' => $outlookAccount->id,
                'email' => $outlookAccount->email,
                'error' => $errorCode,
                'error_description' => $errorDescription,
                'http_status' => $response->status(),
                'permanent' => $isPermanent,
            ]);

            if ($isPermanent) {
                $outlookAccount->update(['status' => AccountStatus::ERROR]);
            }

            throw new Exception('Error refreshing Outlook token: '.$errorDescription);
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
     * @return mixed
     *
     * @throws \Exception
     */
    public function fetchEventsByUser(
        OutlookAccount $outlookAccount,
        string $emailAddress,
        Carbon $startDateTime,
        Carbon $endDateTime,
        bool $useAppOnlyToken = false,
    ): array {
        $this->ensureAuthenticated($outlookAccount);

        // App-only (client-credentials) tokens can always read room mailboxes.
        // Delegated tokens require the signed-in user to have "Full Access" to
        // the room mailbox, which most tenants do not grant by default — so when
        // the account is configured for admin-consent we use the app-only path
        // here too.
        $token = ($useAppOnlyToken && $outlookAccount->isBusiness())
            ? $this->getAppOnlyToken($outlookAccount->tenant_id)
            : $outlookAccount->token;

        $params = [
            'startDateTime' => $startDateTime->utc()->toIso8601String(),
            'endDateTime' => $endDateTime->utc()->toIso8601String(),
            '$select' => 'id,lastModifiedDateTime,subject,body,bodyPreview,isAllDay,location,start,end,onlineMeetingUrl,onlineMeeting',
            '$orderby' => 'createdDateTime',
            '$top' => 100,
        ];

        $response = Http::withToken($token)
            ->withHeaders(['Prefer' => 'outlook.timezone="UTC"'])
            ->get("https://graph.microsoft.com/v1.0/users/$emailAddress/calendarview", $params);

        if (! $response->successful()) {
            $error = Arr::get($response->json(), 'error.message', $response->body());
            logger()->error('Outlook fetching from room failed', [
                'status' => $response->status(),
                'error' => $error,
                'outlook_account_id' => $outlookAccount->id,
                'email' => $emailAddress,
            ]);
            throw new \Exception("Outlook API error for $emailAddress: $error", $response->status());
        }

        return Arr::get($response->json(), 'value') ?? [];
    }

    /**
     * Fetch calendar events from Outlook account.
     *
     * @return mixed
     *
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
            'startDateTime' => $startDateTime->utc()->toIso8601String(),
            'endDateTime' => $endDateTime->utc()->toIso8601String(),
            '$select' => 'id,lastModifiedDateTime,subject,body,bodyPreview,isAllDay,location,start,end,onlineMeetingUrl,onlineMeeting',
            '$orderby' => 'createdDateTime',
            '$top' => 100,
        ];

        $response = Http::withToken($outlookAccount->token)
            ->withHeaders(['Prefer' => 'outlook.timezone="UTC"'])
            ->get("https://graph.microsoft.com/v1.0/me/calendars/$calendarId/calendarview", $params);

        if (! $response->successful()) {
            $error = Arr::get($response->json(), 'error.message', $response->body());
            logger()->error('Outlook fetching from calendar failed', [
                'status' => $response->status(),
                'error' => $error,
                'outlook_account_id' => $outlookAccount->id,
                'calendar_id' => $calendarId,
            ]);
            throw new \Exception("Outlook API error for calendar $calendarId: $error", $response->status());
        }

        return Arr::get($response->json(), 'value') ?? [];
    }

    /**
     * Fetch calendars from the authenticated user's Outlook account.
     *
     * @throws \Exception
     */
    public function fetchCalendars(OutlookAccount $outlookAccount): mixed
    {
        $this->ensureAuthenticated($outlookAccount);

        // Get the current user information
        $response = Http::acceptJson()->withHeaders([
            'Authorization' => 'Bearer '.$outlookAccount->token,
        ])->get('https://graph.microsoft.com/v1.0/me/calendars');

        return Arr::get($response->json(), 'value');
    }

    /**
     * Fetch rooms from the authenticated user's Outlook account.
     *
     * @throws \Exception
     */
    public function fetchRooms(OutlookAccount $outlookAccount): mixed
    {
        $this->ensureAuthenticated($outlookAccount);

        // Get the current user information
        $response = Http::acceptJson()->withHeaders([
            'Authorization' => 'Bearer '.$outlookAccount->token,
        ])->get('https://graph.microsoft.com/v1.0/places/microsoft.graph.room');

        return Arr::get($response->json(), 'value');
    }

    /**
     * Create an event in Outlook calendar.
     *
     * @throws \Exception
     */
    public function createEvent(
        OutlookAccount $outlookAccount,
        Calendar $calendar,
        string $summary,
        Carbon $start,
        Carbon $end,
        ?string $description = null,
        array $attendees = []
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

        if ($description !== null && $description !== '') {
            $eventData['body'] = [
                'contentType' => 'text',
                'content' => $description,
            ];
        }

        if (! empty($attendees)) {
            $eventData['attendees'] = array_map(fn ($email) => [
                'emailAddress' => ['address' => $email],
                'type' => 'required',
            ], $attendees);
        }

        // Determine endpoint and token based on booking method and calendar type
        $useAppToken = $calendar->room
            && $outlookAccount->booking_method === OutlookBookingMethod::ADMIN_CONSENT
            && $outlookAccount->isBusiness();

        if ($useAppToken) {
            // Admin consent: write directly to the room mailbox using an app-only token.
            // The event appears on the room calendar without showing in any personal mailbox.
            $token = $this->getAppOnlyToken($outlookAccount->tenant_id);
            $endpoint = 'https://graph.microsoft.com/v1.0/users/'.urlencode($calendar->calendar_id).'/calendar/events';
        } elseif ($calendar->room) {
            // User account: create in the user's calendar and add the room as a resource
            // attendee — Exchange auto-accepts on the room's behalf.
            $token = $outlookAccount->token;
            $endpoint = 'https://graph.microsoft.com/v1.0/me/calendar/events';
            $eventData['attendees'][] = [
                'emailAddress' => ['address' => $calendar->calendar_id],
                'type' => 'resource',
            ];
        } elseif ($calendar->is_primary) {
            $token = $outlookAccount->token;
            $endpoint = 'https://graph.microsoft.com/v1.0/me/calendar/events';
        } else {
            $token = $outlookAccount->token;
            $endpoint = "https://graph.microsoft.com/v1.0/me/calendars/{$calendar->calendar_id}/events";
        }

        $response = Http::acceptJson()
            ->withHeaders(['Authorization' => 'Bearer '.$token])
            ->post($endpoint, $eventData);

        if (! $response->successful()) {
            $errorCode = $response->json('error.code', '');

            if ($useAppToken && $errorCode === 'ErrorAccessDenied') {
                throw new Exception(
                    'Admin consent booking failed: the app does not have Calendars.ReadWrite application permission. '.
                    'Add Calendars.ReadWrite as an Application permission in your Azure AD app registration and re-run admin consent.',
                    403
                );
            }

            if ($errorCode === 'ErrorAccessDenied') {
                throw new Exception('Access denied by Microsoft 365 — the connected account does not have permission to book this room.', 403);
            }

            throw new Exception('Failed to create Outlook event: '.$response->body());
        }

        return $response->json();
    }

    /**
     * Delete an event from Outlook calendar.
     *
     * @throws \Exception
     */
    public function deleteEvent(
        OutlookAccount $outlookAccount,
        Calendar $calendar,
        string $eventId
    ): void {
        $this->ensureAuthenticated($outlookAccount);

        if ($calendar->room) {
            if ($outlookAccount->booking_method === OutlookBookingMethod::ADMIN_CONSENT) {
                // Admin consent: event lives on the room calendar — delete it directly with an app-only token.
                $token = $this->getAppOnlyToken($outlookAccount->tenant_id);
                $endpoint = 'https://graph.microsoft.com/v1.0/users/'.urlencode($calendar->calendar_id)."/calendar/events/{$eventId}";
            } else {
                // User account: event was created on the user's calendar with the room as a resource attendee.
                // Delete the user's copy; Exchange will automatically remove the room's accepted meeting.
                $token = $outlookAccount->token;
                $endpoint = "https://graph.microsoft.com/v1.0/me/calendar/events/{$eventId}";
            }
        } elseif ($calendar->is_primary) {
            $token = $outlookAccount->token;
            $endpoint = "https://graph.microsoft.com/v1.0/me/calendar/events/{$eventId}";
        } else {
            $token = $outlookAccount->token;
            $endpoint = "https://graph.microsoft.com/v1.0/me/calendars/{$calendar->calendar_id}/events/{$eventId}";
        }

        $response = Http::acceptJson()
            ->withHeaders([
                'Authorization' => 'Bearer '.$token,
            ])
            ->delete($endpoint);

        if (! $response->successful()) {
            throw new Exception('Failed to delete Outlook event: '.$response->body());
        }
    }

    /**
     * Patch the end time of an existing Outlook calendar event.
     */
    public function patchEventEndTime(
        OutlookAccount $outlookAccount,
        Calendar $calendar,
        string $eventId,
        Carbon $newEnd
    ): void {
        $this->ensureAuthenticated($outlookAccount);

        if ($calendar->room) {
            if ($outlookAccount->booking_method === OutlookBookingMethod::ADMIN_CONSENT) {
                $token = $this->getAppOnlyToken($outlookAccount->tenant_id);
                $endpoint = 'https://graph.microsoft.com/v1.0/users/'.urlencode($calendar->calendar_id)."/calendar/events/{$eventId}";
            } else {
                $token = $outlookAccount->token;
                $endpoint = "https://graph.microsoft.com/v1.0/me/calendar/events/{$eventId}";
            }
        } elseif ($calendar->is_primary) {
            $token = $outlookAccount->token;
            $endpoint = "https://graph.microsoft.com/v1.0/me/calendar/events/{$eventId}";
        } else {
            $token = $outlookAccount->token;
            $endpoint = "https://graph.microsoft.com/v1.0/me/calendars/{$calendar->calendar_id}/events/{$eventId}";
        }

        $response = Http::acceptJson()
            ->withHeaders(['Authorization' => 'Bearer '.$token])
            ->patch($endpoint, [
                'end' => [
                    'dateTime' => $newEnd->utc()->toIso8601String(),
                    'timeZone' => 'UTC',
                ],
            ]);

        if (! $response->successful()) {
            throw new \Exception('Failed to update Outlook event end time: '.$response->body());
        }
    }

    /**
     * Create an event subscription for Outlook calendar events.
     *
     * @throws \Exception
     */
    public function createEventSubscriptionByUser(
        OutlookAccount $outlookAccount,
        Display $display,
        string $emailAddress
    ): ?EventSubscription {
        // Try the correct path with /calendar/ first
        try {
            return $this->createEventSubscription($outlookAccount, $display, "/users/$emailAddress/calendar/events");
        } catch (\Exception $e) {
            // If it fails with a resource invalid error, try without /calendar/ path as backup
            if (str_contains($e->getMessage(), 'Resource') && str_contains($e->getMessage(), 'invalid')) {
                logger()->warning('Subscription failed with /calendar/events path, trying /events as backup', [
                    'email' => $emailAddress,
                    'display_id' => $display->id,
                    'error' => $e->getMessage(),
                ]);

                return $this->createEventSubscription($outlookAccount, $display, "/users/$emailAddress/events");
            }
            // Re-throw if it's not a resource invalid error
            throw $e;
        }
    }

    /**
     * Create an event subscription for Outlook calendar events.
     *
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
            'includeResourceData' => 'false',
        ];

        logger()->info('Creating subscription', [
            'data' => $data,
        ]);

        try {
            // Create a subscription with Microsoft Graph
            $response = Http::withToken($outlookAccount->token)
                ->post('https://graph.microsoft.com/v1.0/subscriptions', $data);

            $responseBody = $response->json();
            if (
                $response->failed() ||
                ! Arr::has($responseBody, ['id', 'resource', 'expirationDateTime', 'notificationUrl'])
            ) {
                $statusCode = $response->status();
                $isUserError = $statusCode >= 400 && $statusCode < 500;

                logger()->warning('Creating outlook subscription failed', [
                    'statuscode' => $statusCode,
                    'error' => Arr::get($responseBody, 'error.message'),
                    'is_user_error' => $isUserError,
                ]);

                // Throw exception for user errors (4xx) so the command can handle it
                // Return null for server errors (5xx) to avoid marking display as error
                if ($isUserError) {
                    throw new Exception("Failed to create Outlook subscription: HTTP {$statusCode} - ".($responseBody['error']['message'] ?? $responseBody['message'] ?? 'Unknown error'));
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

        logger()->info('Outlook subscription created', ['subscription_id' => Arr::get($responseBody, 'id')]);

        return $eventSubscription;
    }

    /**
     * Delete an event subscription in Outlook.
     *
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
