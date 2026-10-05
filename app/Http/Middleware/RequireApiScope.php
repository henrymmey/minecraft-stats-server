<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireApiScope
{
    public function handle(Request $request, Closure $next, string $scope): Response
    {
        $key = $request->attributes->get('api_key');

        if (!$key || !$key->hasScope($scope)) {
            return response()->json([
                'error' => [
                    'code' => 'INSUFFICIENT_SCOPE',
                    'message' => 'The API key does not have the required scope.',
                    'request_id' => $request->attributes->get('request_id'),
                ],
            ], 403);
        }

        $request->attributes->set('required_scope', $scope);

        return $next($request);
    }
}
