<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'outlook' => [
        'client_id' => env('OUTLOOK_CLIENT_ID'),
        'client_secret' => env('OUTLOOK_CLIENT_SECRET'),
        'redirect' => env('OUTLOOK_REDIRECT_URI'),
    ],

    'google' => [
        'enabled' => env('GOOGLE_CLIENT_ID') !== null,
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI', 'https://'.env('DOMAIN').'/auth/google/callback'),
        'calendar_redirect' => env('GOOGLE_CALENDAR_REDIRECT_URI', 'https://'.env('DOMAIN').'/google-accounts/callback'),
        'webhook_url' => env('GOOGLE_WEBHOOK_URL', 'https://'.env('DOMAIN').'/api/webhook/google'),
    ],

    'azure_ad' => [
        'enabled' => env('AZURE_AD_CLIENT_ID') !== null,
        'client_id' => env('AZURE_AD_CLIENT_ID'),
        'client_secret' => env('AZURE_AD_CLIENT_SECRET'),
        'redirect' => env('AZURE_AD_REDIRECT_URI', 'https://'.env('DOMAIN').'/outlook-accounts/callback'),
        'tenant_id' => env('AZURE_AD_TENANT_ID', 'common'),
        'webhook_url' => env('OUTLOOK_WEBHOOK_URL', 'https://'.env('DOMAIN').'/api/webhook/outlook')
    ],

    'microsoft' => [
        'enabled' => env('MICROSOFT_CLIENT_ID', env('AZURE_AD_CLIENT_ID')) !== null,
        'client_id' => env('MICROSOFT_CLIENT_ID', env('AZURE_AD_CLIENT_ID')),
        'client_secret' => env('MICROSOFT_CLIENT_SECRET', env('AZURE_AD_CLIENT_SECRET')),
        'redirect' => env('MICROSOFT_REDIRECT_URI', 'https://'.env('DOMAIN').'/auth/microsoft/callback'),
        'proxy' => env('PROXY')  // Optional, will be used for all requests
    ],

    'caldav' => [
        'enabled' => env('CALDAV_ENABLED', true),
        'default_timezone' => env('CALDAV_DEFAULT_TIMEZONE', 'UTC'),
    ],

    'events' => [
        'cache_enabled' => env('EVENTS_CACHE_ENABLED', true),
    ],

    'clarity' => [
        'tag_code' => env('CLARITY_TAG_CODE'),
    ],

    'google_conversion' => [
        'send_to' => env('GOOGLE_CONVERSION_SEND_TO'),
    ],

];
