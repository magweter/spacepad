<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class InstanceData extends Data
{
    public function __construct(
        public string $instanceId,
        public ?string $licenseKey,
        public bool $isSelfHosted,
        public string $version,
        /** @var AccountData[] */
        public array $accounts = [],
        /** @var UserData[] */
        public array $users = [],
    ) {}
}
