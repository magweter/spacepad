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

    'version' => env('SPACEPAD_VERSION'),

    'disable_email_login' => env('DISABLE_EMAIL_LOGIN', false),

    'allowed_logins' => array_filter(array_map('trim', explode(',', env('ALLOWED_LOGINS', '')))), // Comma-separated list of allowed domains or emails

];
