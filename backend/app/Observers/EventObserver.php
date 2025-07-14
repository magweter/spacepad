<?php

namespace App\Observers;

use App\Models\Display;
use App\Models\Event;

class EventObserver
{
    /**
     * Handle the Event "created" event.
     */
    public function created(Event $event): void
    {
        $this->clearDisplayCache($event);
    }

    /**
     * Handle the Event "updated" event.
     */
    public function updated(Event $event): void
    {
        $this->clearDisplayCache($event);
    }

    /**
     * Handle the Event "deleted" event.
     */
    public function deleted(Event $event): void
    {
        $this->clearDisplayCache($event);
    }

    /**
     * Clear the cache for the display's events if display is attached.
     */
    protected function clearDisplayCache(Event $event): void
    {
        if ($event->display_id) {
            cache()->forget(Display::getEventsCacheKeyForDisplay($event->display_id));
        }
    }
}
