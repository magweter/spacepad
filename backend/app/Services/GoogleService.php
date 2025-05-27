<?php

namespace App\Services;

use App\Enums\AccountStatus;
use App\Models\GoogleAccount;
use App\Models\Display;
use App\Models\EventSubscription;
use Exception;
use Google\Client;
use Google\Service\Calendar\Channel;
use Google\Service\Oauth2;
use Google\Service\Calendar;
use Google\Service\Directory;
use Illuminate\Support\Carbon;

class GoogleService
{
    private Client $client;

    public function __construct()
    {
        $this->client = new Client();
        $this->client->setClientId(config('services.google.client_id'));
        $this->client->setClientSecret(config('services.google.client_secret'));
        $this->client->setRedirectUri(config('services.google.calendar_redirect'));
        $this->client->setScopes([
            Oauth2::USERINFO_EMAIL,
            Oauth2::USERINFO_PROFILE,
            Calendar::CALENDAR_READONLY,
            Calendar::CALENDAR_EVENTS_READONLY,
            Directory::ADMIN_DIRECTORY_RESOURCE_CALENDAR_READONLY,
        ]);
        $this->client->setAccessType('offline');
        $this->client->setPrompt('consent');
    }

    /**
     * Handle Google OAuth callback and store tokens in the database.
     *
     * @param string $authCode
     * @return void
     * @throws Exception
     */
    public function authenticateGoogleAccount(string $authCode): void
    {
        $accessToken = $this->client->fetchAccessTokenWithAuthCode($authCode);

        if (isset($accessToken['error'])) {
            throw new Exception('Error authenticating with Google: ' . $accessToken['error_description']);
        }

        logger()->info('Received Google access token:', $accessToken);

        $this->client->setAccessToken($accessToken['access_token']);

        // Get the authenticated user's profile and save tokens
        $googleService = new Oauth2($this->client);
        $googleUserInfo = $googleService->userinfo->get();

        // Save the user's Google account and tokens in the database
        GoogleAccount::updateOrCreate(
            [
                'user_id' => auth()->id(),
                'google_id' => $googleUserInfo->id,
            ],
            [
                'user_id' => auth()->id(),
                'email' => $googleUserInfo->email,
                'name' => $googleUserInfo->name,
                'avatar' => $googleUserInfo->picture,
                'token' => $accessToken['access_token'],
                'refresh_token' => $accessToken['refresh_token'] ?? null,
                'token_expires_at' => now()->addSeconds($accessToken['expires_in']),
                'status' => AccountStatus::CONNECTED,
            ]
        );
    }

    /**
     * Generate Google OAuth URL for authentication.
     *
     * @return string
     */
    public function getAuthUrl(): string
    {
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

        $token = $this->client->fetchAccessTokenWithRefreshToken($account->refresh_token);
        if (isset($token['error'])) {
            throw new Exception('Error authenticating with Google: ' . $tokenData['error_description']);
        }

        $account->update([
            'token' => $token['access_token'],
            'refresh_token' => $token['refresh_token'] ?? $account->refresh_token,
            'token_expires_at' => now()->addSeconds($token['expires_in']),
        ]);
    }

    public function fetchCalendars(GoogleAccount $account): array
    {
        $this->ensureAuthenticated($account);

        $service = new Calendar($this->client);
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
    ): array
    {
        $this->ensureAuthenticated($googleAccount);

        $calendarService = new Calendar($this->client);
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

        $calendarService = new Calendar($this->client);

        try {
            $channel = new Channel();
            $channel->setId(str()->uuid());
            $channel->setType('web_hook');
            $channel->setAddress(config('services.google.webhook_url'));
            $channel->setExpiration(now()->addDays(3)->getTimestampMs());

            $response = $calendarService->events->watch($calendarId, $channel);

            if (!$response->getId()) {
                logger()->error('Creating Google subscription failed', [
                    'response' => $response
                ]);
                return null;
            }

            // Create the subscription record in the database
            $eventSubscription = EventSubscription::create([
                'subscription_id' => $response->getId(),
                'resource' => $calendarId,
                'expiration' => Carbon::createFromTimestamp($response->getExpiration())->toAtomString(),
                'notification_url' => config('services.google.webhook_url'),
                'display_id' => $display->id,
                'google_account_id' => $googleAccount->id,
            ]);

            // Log the creation for debugging
            logger()->info('Google subscription created', ['subscription' => $response]);

            return $eventSubscription;
        } catch (Exception $e) {
            report($e);
            logger()->error('Error creating Google subscription', [
                'error' => $e->getMessage(),
                'calendarId' => $calendarId
            ]);
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
        $this->ensureAuthenticated($googleAccount);

        if ($useApi) {
            try {
                $calendarService = new Calendar($this->client);
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
}
