<?php

return [

    'is_self_hosted' => env('SELF_HOSTED', true),
    'registration_webhook_url' => env('REGISTRATION_WEBHOOK_URL'),
    'onboarding_complete_webhook_url' => env('ONBOARDING_COMPLETE_WEBHOOK_URL'),
    'order_created_webhook_url' => env('ORDER_CREATED_WEBHOOK_URL'),
    'user_not_activated_after_24h_webhook_url' => env('USER_NOT_ACTIVATED_AFTER_24H_WEBHOOK_URL'),
    'user_activated_after_24h_webhook_url' => env('USER_ACTIVATED_AFTER_24H_WEBHOOK_URL'),
    'trial_expired_or_cancelled_webhook_url' => env('TRIAL_EXPIRED_OR_CANCELLED_WEBHOOK_URL'),
    'user_passive_webhook_url' => env('USER_PASSIVE_WEBHOOK_URL'),
    'user_inactive_webhook_url' => env('USER_INACTIVE_WEBHOOK_URL'),
    'account_connected_no_display_webhook_url' => env('ACCOUNT_CONNECTED_NO_DISPLAY_WEBHOOK_URL'),
    'display_created_no_device_webhook_url' => env('DISPLAY_CREATED_NO_DEVICE_WEBHOOK_URL'),
    'trial_day_three_webhook_url' => env('TRIAL_DAY_THREE_WEBHOOK_URL'),
    'trial_ending_soon_webhook_url' => env('TRIAL_ENDING_SOON_WEBHOOK_URL'),
    'trial_ending_tomorrow_webhook_url' => env('TRIAL_ENDING_TOMORROW_WEBHOOK_URL'),

    'license_server' => env('LICENSE_SERVER', 'https://app.spacepad.io'),

    'cloud_hosted_pro_plan_id' => env('CLOUD_HOSTED_PRO_PLAN_ID'),

    // Monthly list price per billable unit on the cloud Pro plan, used to show a workspace
    // what its usage will cost. Display only: Lemon Squeezy remains the authority on what is
    // actually charged. Kept in config rather than read from their API so a page render never
    // waits on a live call. If unset, the estimate is simply left out.
    'cloud_hosted_pro_unit_price' => env('CLOUD_HOSTED_PRO_UNIT_PRICE'),

    // Standard monthly list price per billable unit (display = 1 unit, board = 2 units).
    // Used to compute MRR for users billed manually (outside Lemon Squeezy) via our own
    // accounting system. If unset, manually-billed users still get Pro but contribute 0 MRR.
    'manual_billing_unit_price' => env('MANUAL_BILLING_UNIT_PRICE'),

    'version' => env('SPACEPAD_VERSION'),

    'disable_email_login' => env('DISABLE_EMAIL_LOGIN', false),

    'allowed_logins' => array_filter(array_map('trim', explode(',', env('ALLOWED_LOGINS', '')))), // Comma-separated list of allowed domains or emails

];
