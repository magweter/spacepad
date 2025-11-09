<?php

return [

    // The Google Tag Manager id, e.g. GTM-XXXXXXX
    'id' => env('GTM_ID', ''),

    // Enable or disable script rendering. Useful for local development.
    'enabled' => env('GTM_ENABLED', false),

    // Script domain; keep default unless using a Server-Side GTM custom domain
    'domain' => env('GTM_DOMAIN', 'www.googletagmanager.com'),

    // Session key for flashed data layer values
    'sessionKey' => '_googleTagManager',
];


