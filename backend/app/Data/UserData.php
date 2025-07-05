<?php

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Attributes\MapName;
use Carbon\Carbon;

class UserData extends Data
{
    public function __construct(
        public string $email,
        #[MapName('usage_type')]
        public ?string $usageType,
        #[MapName('is_unlimited')]
        public bool $isUnlimited,
        #[MapName('terms_accepted_at')]
        public ?string $termsAcceptedAt,
    ) {}
}
