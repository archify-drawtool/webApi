<?php

return [
    'tenant_id' => env('ENTRA_TENANT_ID'),
    'client_id' => env('ENTRA_CLIENT_ID'),

    // JWKS endpoint voor token signature verificatie
    'jwks_uri' => 'https://login.microsoftonline.com/'
        .env('ENTRA_TENANT_ID').'/discovery/v2.0/keys',

    // Verwachte issuer voor jouw tenant
    'issuer' => 'https://login.microsoftonline.com/'
        .env('ENTRA_TENANT_ID').'/v2.0',
];
