<?php

namespace App\Data;

use Illuminate\Support\Carbon;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Attributes\MapName;

class InstanceData extends Data
{
    public function __construct(
        #[MapName('instance_key')]
        public string $instanceKey,

        // License & activation
        #[MapName('license_key')]
        public ?string $licenseKey,
        #[MapName('license_valid')]
        public ?bool $licenseValid,
        #[MapName('license_expires_at')]
        public ?Carbon $licenseExpiresAt,

        // Tampering
        #[MapName('is_self_hosted')]
        public bool $isSelfHosted,

        // Usage
        #[MapName('displays_count')]
        public int $displaysCount,
        #[MapName('rooms_count')]
        public int $roomsCount,

        // Telemetry
        public string $version,

        public array $users = [],
    ) {}
}
