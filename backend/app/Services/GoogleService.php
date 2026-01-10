<?php

namespace App\Services;

use App\Enums\AccountStatus;
use App\Models\GoogleAccount;
use App\Models\Display;
use App\Models\EventSubscription;
use App\Models\Calendar;
use Exception;
use Google\Client;
use Google\Service\Calendar\Channel;
use Google\Service\Oauth2;
use Google\Service\Calendar as GoogleCalendar;
use Google\Service\Directory;
use Google\Service\Calendar\Event as GoogleEvent;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use App\Enums\PermissionType;
use App\Enums\GoogleBookingMethod;

class GoogleService
{
    private Client $client;

    public function __construct()
    {
        $this->client = new Client();
        $this->client->setClientId(config('services.google.client_id'));
        $this->client->setClientSecret(config('services.google.client_secret'));
        $this->client->setRedirectUri(config('services.google.calendar_redirect'));
        $this->client->setAccessType('offline');
        $this->client->setPrompt('consent');
    }

    /**
     * Handle Google OAuth callback and store tokens in the database.
     *
     * @param string $authCode
     * @param PermissionType $permissionType
     * @param GoogleBookingMethod $bookingMethod
     * @return GoogleAccount
     * @throws Exception
     */
    public function authenticateGoogleAccount(string $authCode, PermissionType $permissionType = PermissionType::READ, ?GoogleBookingMethod $bookingMethod = null): GoogleAccount
    {
        $accessToken = $this->client->fetchAccessTokenWithAuthCode($authCode);
        if (Arr::exists($accessToken, 'error')) {
            throw new Exception('Error authenticating with Google: ' . Arr::get($accessToken, 'error'));
        }

        logger()->info('Received Google access token:', $accessToken);

        $this->client->setAccessToken($accessToken['access_token']);

        // Get the authenticated user's profile and save tokens
        $googleService = new Oauth2($this->client);
        $googleUserInfo = $googleService->userinfo->get();

        // Save the user's Google account and tokens in the database
        return GoogleAccount::updateOrCreate(
            [
                'user_id' => auth()->id(),
                'google_id' => $googleUserInfo->id,
            ],
            [
                'user_id' => auth()->id(),
                'email' => $googleUserInfo->email,
                'name' => $googleUserInfo->name,
                'avatar' => $googleUserInfo->picture,
                'hosted_domain' => $googleUserInfo->hd,
                'token' => $accessToken['access_token'],
                'refresh_token' => $accessToken['refresh_token'] ?? null,
                'token_expires_at' => now()->addSeconds($accessToken['expires_in']),
                'status' => AccountStatus::CONNECTED,
                'permission_type' => $permissionType,
                'booking_method' => $bookingMethod,
            ]
        );
    }


    /**
     * Determine if a Google account is personal or business
     */
    public function isGoogleBusiness(GoogleAccount $account): bool
    {
        $this->ensureAuthenticated($account);

        try {
            $googleService = new Oauth2($this->client);
            $googleUserInfo = $googleService->userinfo->get();

            // Check if it's a Gmail account
            $isGmail = str_ends_with(strtolower($googleUserInfo->email), '@gmail.com') ||
                str_ends_with(strtolower($googleUserInfo->email), '@googlemail.com');

            // If it's not Gmail and has a hosted domain, it's a business account
            return !$isGmail && isset($googleUserInfo->hd);
        } catch (\Exception $e) {
            logger()->error('Error checking Google account type', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return false;
        }
    }

    /**
     * Generate Google OAuth URL for authentication.
     *
     * @param PermissionType $permissionType
     * @return string
     */
    public function getAuthUrl(PermissionType $permissionType = PermissionType::READ): string
    {
        $scopes = [
            Oauth2::USERINFO_EMAIL,
            Oauth2::USERINFO_PROFILE,
            Directory::ADMIN_DIRECTORY_RESOURCE_CALENDAR_READONLY,
        ];

        if ($permissionType === PermissionType::WRITE) {
            $scopes[] = GoogleCalendar::CALENDAR_EVENTS;
            $scopes[] = GoogleCalendar::CALENDAR_READONLY;
        } else {
            $scopes[] = GoogleCalendar::CALENDAR_EVENTS_READONLY;
            $scopes[] = GoogleCalendar::CALENDAR_READONLY;
        }

        $this->client->setScopes($scopes);

        return $this->client->createAuthUrl();
    }

    private function ensureAuthenticated(GoogleAccount $account): void
    {
        if (!$account->token) {
            throw new Exception('Google account has no token.');
        }

        // Set the access token for API requests
        $this->client->setAccessToken($account->token);

        // Refresh the token if expired
        if ($this->client->isAccessTokenExpired()) {
            $this->refreshToken($account);
        }
    }

    private function refreshToken(GoogleAccount $account): void
    {
        $this->client->setAccessToken($account->token);

        $tokenData = $this->client->fetchAccessTokenWithRefreshToken($account->refresh_token);
        if (Arr::exists($tokenData, 'error')) {
            throw new Exception('Error authenticating with Google: ' . Arr::get($tokenData, 'error'));
        }

        $account->update([
            'token' => $tokenData['access_token'],
            'refresh_token' => $tokenData['refresh_token'] ?? $account->refresh_token,
            'token_expires_at' => now()->addSeconds($tokenData['expires_in']),
        ]);
    }

    public function fetchCalendars(GoogleAccount $account): array
    {
        $this->ensureAuthenticated($account);

        $service = new GoogleCalendar($this->client);
        $calendarList = $service->calendarList->listCalendarList();

        return $calendarList->getItems();
    }

    public function fetchRooms(GoogleAccount $account): array
    {
        $this->ensureAuthenticated($account);

        $service = new Directory($this->client);
        $customerId = 'my_customer'; // Default customer ID for the current domain

        $results = $service->resources_calendars->listResourcesCalendars($customerId);
        return $results->getItems();
    }

    /**
     * @throws Exception
     */
    public function fetchEvents(
        GoogleAccount $googleAccount,
        string $calendarId,
        Carbon $startDateTime,
        Carbon $endDateTime
    ): array {
        $this->ensureAuthenticated($googleAccount);

        $calendarService = new GoogleCalendar($this->client);
        $events = $calendarService->events->listEvents($calendarId, [
            'timeMin' => $startDateTime->toRfc3339String(),
            'timeMax' => $endDateTime->toRfc3339String(),
            'maxResults' => 100,
            'singleEvents' => true,
            'showDeleted' => false,
            'orderBy' => 'startTime'
        ]);

        return $events->getItems();
    }

    /**
     * Create an event in Google Calendar.
     *
     * @param GoogleAccount $googleAccount
     * @param Calendar $calendar
     * @param string $summary
     * @param Carbon $start
     * @param Carbon $end
     * @return GoogleEvent|null
     * @throws Exception
     */
    public function createEvent(
        GoogleAccount $googleAccount,
        Calendar $calendar,
        string $summary,
        Carbon $start,
        Carbon $end
    ): ?GoogleEvent {
        $event = new GoogleEvent();
        $event->setSummary($summary);

        $startDateTime = new \Google\Service\Calendar\EventDateTime();
        $startDateTime->setDateTime($start->toRfc3339String());
        $startDateTime->setTimeZone($start->timezone->getName());
        $event->setStart($startDateTime);

        $endDateTime = new \Google\Service\Calendar\EventDateTime();
        $endDateTime->setDateTime($end->toRfc3339String());
        $endDateTime->setTimeZone($end->timezone->getName());
        $event->setEnd($endDateTime);

        // Get booking method, defaulting to USER_ACCOUNT if null
        $bookingMethod = $googleAccount->booking_method ?? GoogleBookingMethod::USER_ACCOUNT;

        // For workspace accounts with room resources and service account booking method, write directly to room calendar
        if ($calendar->room && $googleAccount->isBusiness() && 
            $bookingMethod === GoogleBookingMethod::SERVICE_ACCOUNT && 
            $googleAccount->service_account_file_path) {
            return $this->createRoomEventWithServiceAccount($googleAccount, $calendar, $event);
        }

        // Fall back to user OAuth method (current account booking method or personal accounts)
        $this->ensureAuthenticated($googleAccount);
        $calendarService = new GoogleCalendar($this->client);

        $calendarId = $calendar->room ? 'primary' : $calendar->calendar_id;

        // For room resources with current account method, create event on user's primary calendar and add room as attendee
        // Room resource calendars are read-only and cannot be written to directly without service account
        if ($calendar->room) {
            $attendee = new \Google\Service\Calendar\EventAttendee();
            $attendee->setEmail($calendar->calendar_id);
            $event->setAttendees([$attendee]);
        }

        try {
            $createdEvent = $calendarService->events->insert($calendarId, $event, [
                'sendUpdates' => 'none'
            ]);
            return $createdEvent;
        } catch (\Exception $e) {
            throw new Exception('Failed to create Google event: ' . $e->getMessage());
        }
    }

    /**
     * Delete an event from Google Calendar.
     *
     * @param GoogleAccount $googleAccount
     * @param Calendar $calendar
     * @param string $eventId
     * @return void
     * @throws Exception
     */
    public function deleteEvent(
        GoogleAccount $googleAccount,
        Calendar $calendar,
        string $eventId
    ): void {
        // Get booking method, defaulting to USER_ACCOUNT if null
        $bookingMethod = $googleAccount->booking_method ?? GoogleBookingMethod::USER_ACCOUNT;

        // For workspace accounts with room resources and service account booking method, delete directly from room calendar
        if ($calendar->room && $googleAccount->isBusiness() && 
            $bookingMethod === GoogleBookingMethod::SERVICE_ACCOUNT && 
            $googleAccount->service_account_file_path) {
            $this->deleteRoomEventWithServiceAccount($googleAccount, $calendar, $eventId);
            return;
        }

        // Fall back to user OAuth method (current account booking method or personal accounts)
        $this->ensureAuthenticated($googleAccount);
        $calendarService = new GoogleCalendar($this->client);

        try {
            if ($calendar->room) {
                $this->deleteRoomEvent($calendarService, $calendar, $eventId);
            } else {
                $calendarService->events->delete($calendar->calendar_id, $eventId, [
                    'sendUpdates' => 'none'
                ]);
            }
        } catch (\Exception $e) {
            throw new Exception('Failed to delete Google event: ' . $e->getMessage());
        }
    }

    /**
     * Delete an event for a room resource.
     * For room resources, events are created on the user's primary calendar,
     * but the eventId we receive is from the room's calendar (from fetchEvents).
     *
     * @param GoogleCalendar $calendarService
     * @param Calendar $calendar
     * @param string $eventId
     * @return void
     * @throws Exception
     */
    private function deleteRoomEvent(
        GoogleCalendar $calendarService,
        Calendar $calendar,
        string $eventId
    ): void {
        // Try deleting from primary calendar first (where we created it)
        try {
            $calendarService->events->delete('primary', $eventId, [
                'sendUpdates' => 'none'
            ]);
        } catch (\Exception $e) {
            // If that fails, try deleting from the room calendar
            // The event might have a different ID on the room calendar
            $calendarService->events->delete($calendar->calendar_id, $eventId, [
                'sendUpdates' => 'none'
            ]);
        }
    }

    /**
     * Create a webhook subscription for Google Calendar events.
     *
     * @param GoogleAccount $googleAccount
     * @param Display $display
     * @param string $calendarId
     * @return EventSubscription|null
     * @throws Exception
     */
    public function createEventSubscription(
        GoogleAccount $googleAccount,
        Display $display,
        string $calendarId
    ): ?EventSubscription {
        $this->ensureAuthenticated($googleAccount);

        $calendarService = new GoogleCalendar($this->client);

        try {
            $channel = new Channel();
            $channel->setId(str()->uuid());
            $channel->setType('web_hook');
            $channel->setAddress(config('services.google.webhook_url'));
            $channel->setExpiration(now()->addDays(3)->getTimestampMs());

            $response = $calendarService->events->watch($calendarId, $channel);

            if (!$response->getId()) {
                logger()->error('Creating Google subscription failed - no subscription ID returned', [
                    'response' => $response,
                ]);
                // This is likely a user error (invalid calendar, permissions, etc.)
                throw new Exception("Failed to create Google subscription: No subscription ID returned");
            }

            // Create the subscription record in the database
            $eventSubscription = EventSubscription::create([
                'subscription_id' => $response->getId(),
                'resource' => $calendarId,
                'expiration' => Carbon::createFromTimestampMs($response->getExpiration()),
                'notification_url' => config('services.google.webhook_url'),
                'display_id' => $display->id,
                'google_account_id' => $googleAccount->id,
            ]);

            // Log the creation for debugging
            logger()->info('Google subscription created', ['subscription' => $response]);

            return $eventSubscription;
        } catch (Exception $e) {
            // Re-throw if it's already a user error exception we just created
            if (str_contains($e->getMessage(), 'Failed to create Google subscription')) {
                throw $e;
            }
            
            // Check if this is a Google API exception with HTTP status code
            $statusCode = $e->getCode();
            $isUserError = $statusCode >= 400 && $statusCode < 500;
            
            // Check exception class name for Google API exceptions
            $exceptionClass = get_class($e);
            
            logger()->error('Error creating Google subscription', [
                'error' => $e->getMessage(),
                'calendarId' => $calendarId,
                'status_code' => $statusCode,
                'is_user_error' => $isUserError,
                'exception_type' => $exceptionClass,
            ]);
            
            // Throw exception for user errors (4xx) so the command can handle it
            // Return null for server errors (5xx) or connection errors to avoid marking display as error
            if ($isUserError) {
                throw new Exception("Failed to create Google subscription: HTTP {$statusCode} - " . $e->getMessage());
            }
            
            // For connection errors, timeouts, etc., don't throw - these are transient
            return null;
        }
    }

    /**
     * Delete a webhook subscription for Google Calendar events.
     *
     * @param GoogleAccount $googleAccount
     * @param EventSubscription $eventSubscription
     * @param bool $useApi
     * @return void
     * @throws Exception
     */
    public function deleteEventSubscription(
        GoogleAccount $googleAccount,
        EventSubscription $eventSubscription,
        bool $useApi = true
    ): void {
        if ($useApi) {
            $this->ensureAuthenticated($googleAccount);

            try {
                $calendarService = new GoogleCalendar($this->client);
                $channel = new Channel();
                $channel->setId($eventSubscription->subscription_id);
                $channel->setResourceId($eventSubscription->resource);

                $calendarService->channels->stop($channel);
            } catch (Exception $e) {
                report($e);
                logger()->error('Error stopping Google subscription', [
                    'error' => $e->getMessage(),
                    'subscriptionId' => $eventSubscription->subscription_id
                ]);
            }
        }

        // Delete the subscription record from the database
        $eventSubscription->delete();

        // Log the deletion for debugging
        logger()->info('Google subscription deleted', ['subscriptionId' => $eventSubscription->id]);
    }

    /**
     * Create a Google Calendar client authenticated with service account.
     *
     * @param GoogleAccount $googleAccount
     * @return Client
     * @throws Exception
     */
    private function getServiceAccountClient(GoogleAccount $googleAccount): Client
    {
        if (!$googleAccount->service_account_file_path) {
            throw new Exception('Service account file path not set for Google account.');
        }

        if (!Storage::exists($googleAccount->service_account_file_path)) {
            throw new Exception('Service account file not found: ' . $googleAccount->service_account_file_path);
        }

        // Read and decrypt the encrypted service account file
        $encryptedContent = Storage::get($googleAccount->service_account_file_path);
        $decryptedContent = Crypt::decryptString($encryptedContent);
        
        // Parse the JSON content
        $serviceAccountData = json_decode($decryptedContent, true);
        if (!$serviceAccountData) {
            throw new Exception('Invalid service account JSON file.');
        }

        $client = new Client();
        // setAuthConfig() can accept either a file path or an array
        // Using array avoids creating temporary files with sensitive data
        $client->setAuthConfig($serviceAccountData);
        
        $scopes = [
            GoogleCalendar::CALENDAR_READONLY,
            GoogleCalendar::CALENDAR_EVENTS,
        ];
        
        $client->setScopes($scopes);
        
        // For domain-wide delegation, impersonate the user who owns the Google account
        // This allows the service account to access resources on behalf of the user
        if ($googleAccount->email) {
            $client->setSubject($googleAccount->email);
        }

        return $client;
    }

    /**
     * Create an event directly on a room resource calendar using service account.
     * This allows booking rooms without using a user's calendar for workspace accounts.
     *
     * @param GoogleAccount $googleAccount
     * @param Calendar $calendar
     * @param GoogleEvent $event
     * @return GoogleEvent
     * @throws Exception
     */
    private function createRoomEventWithServiceAccount(
        GoogleAccount $googleAccount,
        Calendar $calendar,
        GoogleEvent $event
    ): GoogleEvent {
        $client = $this->getServiceAccountClient($googleAccount);
        $calendarService = new GoogleCalendar($client);

        try {
            // With service account and proper permissions, we can write directly to room calendars
            $createdEvent = $calendarService->events->insert($calendar->calendar_id, $event, [
                'sendUpdates' => 'none'
            ]);
            return $createdEvent;
        } catch (\Exception $e) {
            throw new Exception('Failed to create Google room event with service account: ' . $e->getMessage());
        }
    }

    /**
     * Delete an event directly from a room resource calendar using service account.
     *
     * @param GoogleAccount $googleAccount
     * @param Calendar $calendar
     * @param string $eventId
     * @return void
     * @throws Exception
     */
    private function deleteRoomEventWithServiceAccount(
        GoogleAccount $googleAccount,
        Calendar $calendar,
        string $eventId
    ): void {
        $client = $this->getServiceAccountClient($googleAccount);
        $calendarService = new GoogleCalendar($client);

        try {
            // With service account and proper permissions, we can delete directly from room calendars
            $calendarService->events->delete($calendar->calendar_id, $eventId, [
                'sendUpdates' => 'none'
            ]);
        } catch (\Exception $e) {
            throw new Exception('Failed to delete Google room event with service account: ' . $e->getMessage());
        }
    }
}
