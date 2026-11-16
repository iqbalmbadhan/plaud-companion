<?php

// PLAUD Embedded (partner programme). Credentials stay on the server only:
// the mobile app receives short-lived per-user tokens, never the client secret.
return [
    'base_url' => rtrim((string) env('PLAUD_API_BASE', 'https://platform-us.plaud.ai'), '/'),
    'client_id' => env('PLAUD_CLIENT_ID'),
    'client_secret' => env('PLAUD_CLIENT_SECRET'),
    'user_token_ttl' => (int) env('PLAUD_USER_TOKEN_TTL', 86400),
    'timeout' => 15,
];
