<?php

namespace App\Data;

use Spatie\LaravelData\Data;
use Carbon\Carbon;

class DisplayData extends Data
{
    public function __construct(
        public string $id,
        public string $name,
        public bool $has_calendar_display,
        public ?Carbon $created_at = null,
        public ?Carbon $updated_at = null,
    ) {}
}
