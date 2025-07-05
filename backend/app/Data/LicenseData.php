<?php

namespace App\Data;

use App\Models\Instance;
use Spatie\LaravelData\Data;
use Carbon\Carbon;

class LicenseData extends Data
{
    public function __construct(
        public ?string $licenseKey,
        public ?bool $valid = false,
        public ?Carbon $expiresAt = null,
    ) {}

    public static function fromModel(Instance $instance): self
    {
        return new self(
            licenseKey: $instance->license_key,
            valid: $instance->license_valid,
            expiresAt: $instance->license_expires_at,
        );
    }
}
