<?php

namespace App\Services;

use App\Models\CalDAVAccount;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Sabre\DAV\Client;
use Sabre\DAV\Xml\Property\ResourceType;
use Sabre\HTTP\ClientException;
use Sabre\VObject\Reader;

class CalDAVService
{
    private Client $client;

    public function __construct()
    {
        $this->client = new Client([
            'baseUri' => '',
            'userName' => '',
            'password' => '',
        ]);
    }

    private function configureClient(CalDAVAccount $account): void
    {
        $this->client = new Client([
            'baseUri' => Str::endsWith($account->url, '/') ? $account->url : rtrim($account->url, '/'),
            'userName' => $account->username,
            'password' => $account->password,
        ]);
    }

    public function fetchCalendars(CalDAVAccount $account): array
    {
        $this->configureClient($account);

        try {
            $response = $this->client->propFind("$account->url/calendars/$account->username/", [
                '{DAV:}resourcetype',
                '{DAV:}displayname',
                '{urn:ietf:params:xml:ns:caldav}calendar-description',
            ], 1);

            $calendars = [];
            foreach ($response as $path => $properties) {
                if (!isset($properties['{DAV:}resourcetype'])) {
                    continue;
                }

                $resourceType = $properties['{DAV:}resourcetype'];
                if (!$resourceType instanceof ResourceType ||
                    !$resourceType->is('{urn:ietf:params:xml:ns:caldav}calendar')) {
                    continue;
                }

                $calendars[] = [
                    'id' => $path,
                    'name' => $properties['{DAV:}displayname'] ?? basename($path),
                    'description' => $properties['{urn:ietf:params:xml:ns:caldav}calendar-description'] ?? '',
                ];
            }

            return $calendars;
        } catch (\Exception $e) {
            throw new \Exception("Failed to fetch calendars: " . $e->getMessage());
        }
    }

    public function fetchEvents(
        CalDAVAccount $caldavAccount,
        string $calendarId,
        Carbon $startDateTime,
        Carbon $endDateTime
    ): array {
        $this->configureClient($caldavAccount);

        $query = <<<XML
<?xml version="1.0" encoding="utf-8" ?>
<C:calendar-query xmlns:D="DAV:" xmlns:C="urn:ietf:params:xml:ns:caldav">
    <D:prop>
        <D:getetag/>
        <C:calendar-data/>
    </D:prop>
    <C:filter>
        <C:comp-filter name="VCALENDAR">
            <C:comp-filter name="VEVENT">
                <C:time-range start="{$startDateTime->format('Ymd\THis\Z')}" end="{$endDateTime->format('Ymd\THis\Z')}"/>
            </C:comp-filter>
        </C:comp-filter>
    </C:filter>
</C:calendar-query>
XML;

        try {
            $response = $this->client->request('REPORT', $calendarId, $query, [
                'Depth' => 1,
                'Content-Type' => 'application/xml; charset=utf-8',
            ]);

            if ($response['statusCode'] !== 207) {
                throw new \Exception("Unexpected status code {$response['statusCode']}");
            }

            // Parse multi-status response with calendar-data entries
            $body = $response['body'];
            $events = [];

            preg_match_all('/<cal:calendar-data[^>]*>(.*?)<\/cal:calendar-data>/s', $body, $matches);

            foreach ($matches[1] as $icalData) {
                $vcalendar = Reader::read($icalData);
                foreach ($vcalendar->select('VEVENT') as $vevent) {
                    $start = $vevent->DTSTART->getDateTime();
                    $end = $vevent->DTEND->getDateTime();

                    $events[] = [
                        'id' => (string) $vevent->UID,
                        'summary' => (string) $vevent->SUMMARY,
                        'description' => (string) $vevent->DESCRIPTION,
                        'location' => (string) $vevent->LOCATION,
                        'start' => $start->format('Y-m-d\TH:i:sP'),
                        'end' => $end->format('Y-m-d\TH:i:sP'),
                        'timezone' => $start->getTimezone()->getName() ?? $end->getTimezone()->getName() ?? 'UTC',
                        'isAllDay' => $vevent->DTSTART->hasTime() === false,
                    ];
                }
            }

            return $events;
        } catch (\Exception $e) {
            throw new \Exception("CalDAV request failed: " . $e->getMessage());
        }
    }

    /**
     * Check if the CalDAV server is accessible and credentials are valid
     *
     * @param string $url The CalDAV server URL
     * @param string $username The username for authentication
     * @param string $password The password for authentication
     * @return array{success: bool, message: string} Connection test result
     */
    public function checkConnection(string $url, string $username, string $password): array
    {
        try {
            $settings = [
                'baseUri' => rtrim($url, '/'),
                'userName' => $username,
                'password' => $password,
            ];

            $client = new Client($settings);

            // Try to fetch the principal URL to verify connection
            $response = $client->propFind('', [
                '{DAV:}current-user-principal'
            ], 0);

            if (isset($response['{DAV:}current-user-principal'])) {
                return [
                    'success' => true,
                    'message' => 'Successfully connected to CalDAV server'
                ];
            }

            return [
                'success' => false,
                'message' => 'Failed to connect to CalDAV server: Could not find principal URL'
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to connect to CalDAV server: ' . $e->getMessage()
            ];
        }
    }
}
