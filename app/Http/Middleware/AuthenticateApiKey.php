<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use App\Services\ApiKeys\ApiKeyService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiKey
{
    public function __construct(private readonly ApiKeyService $apiKeys)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $header = $request->header('Authorization', '');

        if (!preg_match('/^Bearer\s+(.+)$/i', $header, $matches)) {
            return $this->error('INVALID_API_KEY', 'A Bearer API key is required.', 401);
        }

        $key = $this->apiKeys->resolve(trim($matches[1]));

        if (!$key instanceof ApiKey || !$key->isUsable()) {
            return $this->error('INVALID_API_KEY', 'The API key is invalid, expired or revoked.', 401);
        }

        $request->attributes->set('api_key', $key);

        return $next($request);
    }

    private function error(string $code, string $message, int $status): Response
    {
        return response()->json([
            'error' => [
                'code' => $code,
                'message' => $message,
                'request_id' => request()->attributes->get('request_id'),
            ],
        ], $status);
    }
}
