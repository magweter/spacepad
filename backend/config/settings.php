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

    // The standard monthly list price per billable unit (display = 1 unit, board = 2 units).
    //
    // One number with two readers, deliberately not two settings: they would be the same
    // price under different names, free to drift, and then the estimate a customer is shown
    // no longer matches the MRR we book.
    //
    //  - Manually billed workspaces (invoiced through our own accounting rather than Lemon
    //    Squeezy) have their MRR computed from it. A workspace can override it with a
    //    negotiated price of its own, see workspaces.manual_billing_unit_price.
    //  - Workspaces on the cloud Pro plan are shown what their usage costs per month. That is
    //    display only: Lemon Squeezy stays the authority on what is actually charged. Read
    //    from config rather than their API so a page render never waits on a live call.
    //
    // Reads MANUAL_BILLING_UNIT_PRICE as a fallback so environments that set the old name
    // keep working. Unset, manually billed workspaces contribute 0 MRR and the cost estimate
    // is left out.
    'unit_price' => env('UNIT_PRICE', env('MANUAL_BILLING_UNIT_PRICE')),

    'version' => env('SPACEPAD_VERSION'),

    'disable_email_login' => env('DISABLE_EMAIL_LOGIN', false),

    'allowed_logins' => array_filter(array_map('trim', explode(',', env('ALLOWED_LOGINS', '')))), // Comma-separated list of allowed domains or emails

    // Fixed pairing code for the app store review account. Left empty everywhere except the
    // hosted production environment: a store reviewer needs a code that still works whenever
    // they get around to testing, and that a second reviewer can use again afterwards. The
    // rotating 30 minute code guarantees neither, which is a rejection waiting to happen.
    //
    // Must be exactly 6 digits, the app's connect screen accepts nothing else. Clear these
    // once the review is through: the code never expires and is never consumed.
    'review_connect_code' => env('REVIEW_CONNECT_CODE'),
    'review_connect_user_id' => env('REVIEW_CONNECT_USER_ID'),
    'review_connect_workspace_id' => env('REVIEW_CONNECT_WORKSPACE_ID'),

];
