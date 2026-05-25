<?php

namespace App\Data;

use App\Models\Event;

class RoomStatus
{
    public function __construct(
        public readonly string $status,
        public readonly ?Event $currentEvent,
        public readonly ?Event $nextEvent,
        public readonly string $timezone,
    ) {}
}
