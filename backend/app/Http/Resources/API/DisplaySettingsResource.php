<?php

namespace App\Http\Resources\API;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DisplaySettingsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     */
    public function toArray($request): array
    {
        return [
            'check_in_enabled' => $this->isCheckInEnabled(),
            'booking_enabled' => $this->isBookingEnabled(),
            'calendar_enabled' => $this->isCalendarEnabled(),
            'hide_admin_actions' => $this->isAdminActionsHidden(),
            'check_in_minutes' => $this->getCheckInMinutes(),
            'check_in_grace_period' => $this->getCheckInGracePeriod(),
            'text_available' => $this->getAvailableText(),
            'text_transitioning' => $this->getTransitioningText(),
            'text_reserved' => $this->getReservedText(),
            'text_checkin' => $this->getCheckInText(),
            'show_meeting_title' => $this->getShowMeetingTitle(),
            'logo_url' => $this->getLogoUrl(),
            'background_image_url' => $this->getBackgroundImageUrl(),
            'font_family' => $this->getFontFamily(),

            // Feature flags
            'has_custom_booking' => $this->hasCustomBooking(),
        ];
    }
}
