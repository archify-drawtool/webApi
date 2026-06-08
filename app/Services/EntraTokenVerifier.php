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
        $jwks = Cache::remember('entra_jwks', 86400, function () {
            return Http::get(config('entra.jwks_uri'))->json();
        });

        // Microsoft's JWKS mist soms het 'alg' veld — firebase/php-jwt vereist dit
        // RS256 is het standaard algoritme voor Microsoft Entra ID tokens
        $jwks['keys'] = array_map(function ($key) {
            if (! isset($key['alg'])) {
                $key['alg'] = 'RS256';
            }

            return $key;
        }, $jwks['keys'] ?? []);

        // Wis de cache zodat de gefixte versie opgeslagen wordt
        Cache::forget('entra_jwks');
        Cache::put('entra_jwks', $jwks, 86400);

        $keys = JWK::parseKeySet($jwks);
        $payload = (array) JWT::decode($idToken, $keys);

        // Bij multi-tenant (common) is de issuer dynamisch per tenant.
        // We controleren of de issuer het verwachte patroon volgt.
        $iss = $payload['iss'] ?? null;
        $issuerPattern = '/^https:\/\/login\.microsoftonline\.com\/[a-f0-9\-]+\/v2\.0$/';
        if (! $iss || ! preg_match($issuerPattern, $iss)) {
            throw new \Exception('Invalid token issuer');
        }

        if (($payload['aud'] ?? null) !== config('entra.client_id')) {
            throw new \Exception('Invalid token audience');
        }

        return $payload;
    }
}
