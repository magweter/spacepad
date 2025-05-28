<?php

return [

    'is_self_hosted' => env('SELF_HOSTED', true),
    'registration_webhook_url' => env('REGISTRATION_WEBHOOK_URL'),
    'onboarding_complete_webhook_url' => env('ONBOARDING_COMPLETE_WEBHOOK_URL'),
    'order_created_webhook_url' => env('ORDER_CREATED_WEBHOOK_URL'),

    'free_trial_days' => env('FREE_TRIAL_DAYS', 7),
    'cloud_plan_id' => env('CLOUD_PLAN_ID'),

];
