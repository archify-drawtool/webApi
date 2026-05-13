<?php

namespace App\Services;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class EntraTokenVerifier
{
    public function verify(string $idToken): array
    {
        // 1. Haal Microsoft's public keys op (24u cache)
        $jwks = Cache::remember('entra_jwks', 86400, function () {
            return Http::get(config('entra.jwks_uri'))->json();
        });

        // 2. Verify signature & decode
        $keys = JWK::parseKeySet($jwks);
        $payload = (array) JWT::decode($idToken, $keys);

        // 3. Validate issuer (alleen onze tenant)
        if (($payload['iss'] ?? null) !== config('entra.issuer')) {
            throw new \Exception('Invalid token issuer');
        }

        // 4. Validate audience (must match our client_id)
        if (($payload['aud'] ?? null) !== config('entra.client_id')) {
            throw new \Exception('Invalid token audience');
        }

        return $payload;
    }
}
