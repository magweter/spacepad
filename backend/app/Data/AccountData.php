<?php

namespace App\Data;

use Spatie\LaravelData\Data;
use Carbon\Carbon;

class AccountData extends Data
{
    public function __construct(
        public string $email,
        public string $status,
        public string $provider,
        public ?bool $isBusiness = null,
    ) {}
}
