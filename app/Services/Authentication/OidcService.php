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

        Cache::put(
            $this->transactionCacheKey($state),
            [
                'nonce' => $nonce,
                'verifier' => $verifier,
            ],
            now()->addMinutes(10),
        );

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
        $transaction = Cache::pull($this->transactionCacheKey($state));

        if (!is_array($transaction)) {
            throw new RuntimeException('OIDC state validation failed.');
        }

        $verifier = (string) ($transaction['verifier'] ?? '');
        $nonce = (string) ($transaction['nonce'] ?? '');

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

        $authMethods = $this->tokenEndpointAuthMethods($metadata);
        $response = null;
        $attemptedMethods = [];

        foreach ($authMethods as $authMethod) {
            $attemptedMethods[] = $authMethod;

            if ($authMethod === 'client_secret_basic') {
                $response = $http
                    ->withBasicAuth(
                        (string) config('services.oidc.client_id'),
                        (string) config('services.oidc.client_secret'),
                    )
                    ->asForm()
                    ->post($metadata['token_endpoint'], $tokenRequest);
            } elseif ($authMethod === 'client_secret_post') {
                $response = $http
                    ->asForm()
                    ->post($metadata['token_endpoint'], [
                        ...$tokenRequest,
                        'client_secret' => (string) config('services.oidc.client_secret'),
                    ]);
            } elseif ($authMethod === 'none') {
                $response = $http
                    ->asForm()
                    ->post($metadata['token_endpoint'], $tokenRequest);
            } else {
                throw new RuntimeException(
                    sprintf('Unsupported OIDC token endpoint authentication method: %s.', $authMethod),
                );
            }

            if ($response->successful()) {
                break;
            }

            if ($response->json('error') !== 'invalid_client') {
                break;
            }
        }

        if ($response === null) {
            throw new RuntimeException('OIDC token endpoint authentication could not be attempted.');
        }

        if (!$response->successful()) {
            if ($response->json('error') === 'invalid_client') {
                throw new RuntimeException(
                    'OIDC token endpoint rejected client authentication after trying: '.
                    implode(', ', $attemptedMethods).
                    '. Check OIDC_CLIENT_ID, OIDC_CLIENT_SECRET, and OIDC_TOKEN_ENDPOINT_AUTH_METHOD.',
                );
            }

            $response->throw();
        }

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

    private function tokenEndpointAuthMethods(array $metadata): array
    {
        $configured = trim((string) config('services.oidc.token_endpoint_auth_method', 'auto'));

        if ($configured !== '' && $configured !== 'auto') {
            return [$configured];
        }

        $advertised = $metadata['token_endpoint_auth_methods_supported'] ?? [];

        if (!is_array($advertised)) {
            $advertised = [];
        }

        $methods = array_values(array_intersect(
            $advertised,
            ['client_secret_post', 'client_secret_basic', 'none'],
        ));

        if ($methods === []) {
            return ['client_secret_basic'];
        }

        return $methods;
    }

    private function transactionCacheKey(string $state): string
    {
        return 'oidc.transaction.'.hash('sha256', $state);
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
