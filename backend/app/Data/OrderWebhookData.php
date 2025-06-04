<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class OrderWebhookData extends Data
{
    public function __construct(
        public string $id,
        public string $total,
        public string $status,
    ) {
    }
}
