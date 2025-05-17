<?php

namespace App\Services;

use App\Models\GoogleAccount;
use Exception;
use Google\Client;
use Google\Service\Oauth2;
use Google\Service\Calendar;
use Google\Service\Directory;

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
                'email' => $googleUserInfo->email,
                'name' => $googleUserInfo->name,
                'avatar' => $googleUserInfo->picture,
                'token' => $accessToken['access_token'],
                'refresh_token' => $accessToken['refresh_token'] ?? null,
                'token_expires_at' => now()->addSeconds($accessToken['expires_in']),
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
        $this->client->setAccessToken([
            'access_token' => $account->token,
            'refresh_token' => $account->refresh_token,
        ]);

        $token = $this->client->fetchAccessTokenWithRefreshToken($account->refresh_token);

        $account->update([
            'access_token' => $token['access_token'],
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

    public function fetchEvents(GoogleAccount $account, string $calendarId, ?string $timeMin = null, ?string $timeMax = null): array
    {
        $this->ensureAuthenticated($account);

        $service = new Calendar($this->client);
        $optParams = [
            'maxResults' => 100,
            'orderBy' => 'startTime',
            'singleEvents' => true,
        ];

        if ($timeMin) {
            $optParams['timeMin'] = $timeMin;
        }
        if ($timeMax) {
            $optParams['timeMax'] = $timeMax;
        }

        $results = $service->events->listEvents($calendarId, $optParams);
        $events = $results->getItems();

        return collect($events)->map(function ($event) {
            $start = $event->getStart();
            $end = $event->getEnd();

            return [
                'id' => $event->getId(),
                'summary' => $event->getSummary(),
                'start' => $start->getDateTime() ?? $start->getDate(),
                'end' => $end->getDateTime() ?? $end->getDate(),
                'is_all_day' => !$start->getDateTime(),
            ];
        })->toArray();
    }
}
