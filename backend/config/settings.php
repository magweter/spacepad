<?php

return [

    'is_self_hosted' => env('SELF_HOSTED', true),
    'registration_webhook_url' => env('REGISTRATION_WEBHOOK_URL'),
    'onboarding_complete_webhook_url' => env('ONBOARDING_COMPLETE_WEBHOOK_URL'),
    'order_created_webhook_url' => env('ORDER_CREATED_WEBHOOK_URL'),

    'self_hosted_pro_plan_id' => '538269',
    'cloud_hosted_pro_plan_id' => env('CLOUD_HOSTED_PRO_PLAN_ID'),

];
