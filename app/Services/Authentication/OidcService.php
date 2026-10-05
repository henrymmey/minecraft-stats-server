<?php

namespace App\Services\Authentication;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class OidcService
{
    public function authorizationUrl(): string
    {
        $metadata = $this->metadata();
        $state = Str::random(64);
        $nonce = Str::random(64);
        $verifier = rtrim(strtr(base64_encode(random_bytes(64)), '+/', '-_'), '=');
        $challenge = rtrim(strtr(
            base64_encode(hash('sha256', $verifier, true)),
            '+/',
            '-_',
        ), '=');

        session([
            'oidc.state' => $state,
            'oidc.nonce' => $nonce,
            'oidc.verifier' => $verifier,
        ]);

        return $metadata['authorization_endpoint'].'?'.http_build_query([
            'response_type' => 'code',
            'client_id' => config('services.oidc.client_id'),
            'redirect_uri' => config('services.oidc.redirect_uri'),
            'scope' => implode(' ', config('services.oidc.scopes', ['openid', 'profile', 'email'])),
            'state' => $state,
            'nonce' => $nonce,
            'code_challenge' => $challenge,
            'code_challenge_method' => 'S256',
        ], '', '&', PHP_QUERY_RFC3986);
    }

    public function authenticate(string $state, string $code): array
    {
        if (!hash_equals((string) session('oidc.state', ''), $state)) {
            throw new RuntimeException('OIDC state validation failed.');
        }

        $verifier = (string) session('oidc.verifier', '');
        $nonce = (string) session('oidc.nonce', '');

        session()->forget(['oidc.state', 'oidc.nonce', 'oidc.verifier']);

        if ($verifier === '' || $nonce === '') {
            throw new RuntimeException('OIDC transaction is missing or expired.');
        }

        $metadata = $this->metadata();
        $http = Http::acceptJson()->timeout(10);

        $tokenRequest = [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => config('services.oidc.redirect_uri'),
            'client_id' => config('services.oidc.client_id'),
            'code_verifier' => $verifier,
        ];

        $authMethods = $metadata['token_endpoint_auth_methods_supported'] ?? ['client_secret_basic'];

        if (in_array('client_secret_basic', $authMethods, true)) {
            $response = $http
                ->withBasicAuth(
                    (string) config('services.oidc.client_id'),
                    (string) config('services.oidc.client_secret'),
                )
                ->asForm()
                ->post($metadata['token_endpoint'], $tokenRequest);
        } else {
            $tokenRequest['client_secret'] = config('services.oidc.client_secret');
            $response = $http->asForm()->post($metadata['token_endpoint'], $tokenRequest);
        }

        $response->throw();
        $tokens = $response->json();

        if (!is_array($tokens) || empty($tokens['id_token'])) {
            throw new RuntimeException('OIDC provider did not return an ID token.');
        }

        $claims = $this->verifyIdToken($tokens['id_token'], $metadata['jwks_uri'], $nonce);

        return [
            'tokens' => $tokens,
            'claims' => $claims,
            'metadata' => $metadata,
        ];
    }

    private function verifyIdToken(string $idToken, string $jwksUri, string $expectedNonce): array
    {
        $jwks = Cache::remember(
            'oidc.jwks.'.hash('sha256', $jwksUri),
            now()->addHour(),
            fn () => Http::acceptJson()->timeout(10)->get($jwksUri)->throw()->json(),
        );

        $claims = (array) JWT::decode($idToken, JWK::parseKeySet($jwks));

        $issuer = (string) config('services.oidc.issuer');
        $clientId = (string) config('services.oidc.client_id');

        if (($claims['iss'] ?? null) !== $issuer) {
            throw new RuntimeException('OIDC issuer validation failed.');
        }

        $aud = $claims['aud'] ?? null;
        $audiences = is_array($aud) ? $aud : [$aud];

        if (!in_array($clientId, $audiences, true)) {
            throw new RuntimeException('OIDC audience validation failed.');
        }

        if (count($audiences) > 1 && ($claims['azp'] ?? null) !== $clientId) {
            throw new RuntimeException('OIDC authorized-party validation failed.');
        }

        if (($claims['nonce'] ?? null) !== $expectedNonce) {
            throw new RuntimeException('OIDC nonce validation failed.');
        }

        $subject = $claims['sub'] ?? null;

        if (!is_string($subject) || $subject === '') {
            throw new RuntimeException('OIDC subject is missing.');
        }

        return $claims;
    }

    private function metadata(): array
    {
        $issuer = rtrim((string) config('services.oidc.issuer'), '/');

        if ($issuer === '' || !filter_var($issuer, FILTER_VALIDATE_URL)) {
            throw new RuntimeException('OIDC issuer is not configured correctly.');
        }

        if (config('services.oidc.require_https', true) && !str_starts_with($issuer, 'https://')) {
            throw new RuntimeException('OIDC issuer must use HTTPS in production.');
        }

        return Cache::remember(
            'oidc.metadata.'.hash('sha256', $issuer),
            now()->addHour(),
            function () use ($issuer): array {
                return Http::acceptJson()
                    ->timeout(10)
                    ->get($issuer.'/.well-known/openid-configuration')
                    ->throw()
                    ->json();
            },
        );
    }
}
