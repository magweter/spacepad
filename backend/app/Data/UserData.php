<?php

namespace App\Data;

use Spatie\LaravelData\Data;
use Carbon\Carbon;

class UserData extends Data
{
    public function __construct(
        public string $email,
        public string $status,
        public int $numDisplays,
        public int $numRooms,
        public ?string $usageType,
        public ?bool $isUnlimited,
        public ?Carbon $termsAcceptedAt = null,
    ) {}
}
