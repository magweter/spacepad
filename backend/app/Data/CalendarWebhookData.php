<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class CalendarWebhookData extends Data
{
    public function __construct(
        public string $id,
        public string $name,
        public ?string $googleAccountId,
        public ?string $outlookAccountId,
        public ?string $caldavAccountId,
        public array &$providers = []
    ) {
        if ($googleAccountId) {
            $providers[] = 'Google';
        }
        if ($outlookAccountId) {
            $providers[] = 'Outlook';
        }
        if ($caldavAccountId) {
            $providers[] = 'CalDAV';
        }
    }

    public function excludeProperties(): array
    {
        return [
            'googleAccountId',
            'outlookAccountId',
            'caldavAccountId',
        ];
    }
}
