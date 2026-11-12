<?php

namespace App\Enums;

/**
 * The steps from signing up to paying, as stored in funnel_events.event.
 *
 * Only ever add cases: the value is what reporting groups on.
 */
enum FunnelStep: string
{
    case SIGNED_UP = 'signed_up';
    case CALENDAR_CONNECTED = 'calendar_connected';
    case DISPLAY_CREATED = 'display_created';
    case DEVICE_PAIRED = 'device_paired';
    case SUBSCRIBED = 'subscribed';
    case PROFILE_CARD_SHOWN = 'profile_card_shown';
    case PROFILE_CARD_DISMISSED = 'profile_card_dismissed';
    case ONBOARDING_STEP_VIEWED = 'onboarding_step_viewed';
    case ONBOARDING_STEP_COMPLETED = 'onboarding_step_completed';
    case ONBOARDING_STEP_SKIPPED = 'onboarding_step_skipped';
    case ONBOARDING_COMPLETED = 'onboarding_completed';
    case ONBOARDING_SKIPPED = 'onboarding_skipped';
    case IT_REQUEST_SENT = 'it_request_sent';
    case JOIN_REQUESTED = 'join_requested';
    case JOIN_DECLINED_BY_USER = 'join_separate_workspace';
    case JOIN_APPROVED = 'join_approved';
}
