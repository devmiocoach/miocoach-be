<?php

return [

    'private_key' => env('JWT_PRIVATE_KEY'),
    'public_key' => env('JWT_PUBLIC_KEY'),

    'access_ttl' => env('JWT_ACCESS_TTL', 3600),
    'refresh_ttl' => env('JWT_REFRESH_TTL', 2592000),
    'temp_ttl' => env('JWT_TEMP_TTL', 300),
    'reset_ttl' => env('JWT_RESET_TTL', 3600),
    'verify_ttl' => env('JWT_VERIFY_TTL', 86400),
    'invite_ttl' => env('JWT_INVITE_TTL', 7 * 24 * 3600),

];
