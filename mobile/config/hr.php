<?php

return [

    /*
    |--------------------------------------------------------------------------
    | HR backend API
    |--------------------------------------------------------------------------
    |
    | Base URL of the backend's employee API (see backend/API.md). The app talks to
    | it over HTTP(S) only; it never touches the backend database.
    |
    | Where this must point depends on where the PHP code runs:
    |   - Jump / Docker dev  -> PHP runs on the dev PC: use the PC's LAN IP (or http://backend:8000/api/v1
    |                           inside the compose network).
    |   - Installed app      -> PHP runs on the phone: it must be a URL the phone can reach, and
    |                           production builds should use HTTPS (iOS/Android block plain HTTP).
    |
    */

    'api_url' => env('HR_API_URL', 'http://172.16.14.106:8000/api/v1'),

    'timeout' => (int) env('HR_API_TIMEOUT', 15),

    // Name shown for this device in the backend's token list.
    'device_name' => env('HR_DEVICE_NAME', 'mobile-app'),

];
