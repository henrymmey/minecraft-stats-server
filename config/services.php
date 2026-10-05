<?php

return [
    'api_keys' => [
        'pepper' => env('API_KEY_PEPPER', ''),
    ],

    'oidc' => [
        'issuer' => env('OIDC_ISSUER'),
        'client_id' => env('OIDC_CLIENT_ID'),
        'client_secret' => env('OIDC_CLIENT_SECRET'),
        'redirect_uri' => env('OIDC_REDIRECT_URI', env('APP_URL').'/auth/callback'),
        'scopes' => preg_split('/\s+/', trim((string) env('OIDC_SCOPES', 'openid profile email'))) ?: [],
    ],
];
