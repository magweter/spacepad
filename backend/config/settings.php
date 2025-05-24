<?php

return [

    'is_self_hosted' => env('SELF_HOSTED', true),
    'registration_webhook_url' => env('REGISTRATION_WEBHOOK_URL', null),
    'onboarding_complete_webhook_url' => env('ONBOARDING_COMPLETE_WEBHOOK_URL', null),

    'free_trial_days' => env('FREE_TRIAL_DAYS', 7),
    'cloud_plan_id' => env('CLOUD_PLAN_ID'),

];
