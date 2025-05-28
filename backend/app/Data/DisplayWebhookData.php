<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class DisplayWebhookData extends Data
{
    public function __construct(
        public string $id,
        public string $name,
    ) {
    }
}
