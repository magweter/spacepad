<?php

namespace App\Data;

use Illuminate\Support\Carbon;
use Spatie\LaravelData\Data;

class UserWebhookData extends Data
{
    public function __construct(
        public string $id,
        public string $name,
        public string $email,
        public string $status,
        public ?Carbon $emailVerifiedAt,
        public ?string $microsoftId,
        public ?string $googleId,
        public ?bool $isBillingExempt,
        public ?bool $isUnlimited,
        public ?Carbon $lastActivityAt,
        public Carbon $createdAt,
        public Carbon $updatedAt,
        public array &$providers = []
    ) {
        if ($emailVerifiedAt) {
            $providers[] = 'Email';
        }
        if ($microsoftId) {
            $providers[] = 'Microsoft';
        }
        if ($googleId) {
            $providers[] = 'Google';
        }
    }

    public function excludeProperties(): array
    {
        return [
            'microsoftId',
            'googleId',
        ];
    }
}
