<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\Authentication\BootstrapService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BootstrapController
{
    public function __construct(private readonly BootstrapService $bootstrap)
    {
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'token' => ['required', 'string', 'max:300'],
        ]);

        try {
            $workspace = $this->bootstrap->consume(
                $request->string('token')->toString(),
                $request->user('web'),
            );
        } catch (\Throwable $e) {
            return response()->json([
                'error' => [
                    'code' => 'INVALID_BOOTSTRAP_TOKEN',
                    'message' => $e->getMessage(),
                    'request_id' => $request->attributes->get('request_id'),
                ],
            ], 422);
        }

        return response()->json([
            'data' => [
                'workspace_id' => $workspace->id,
                'role' => 'owner',
            ],
        ], 201);
    }
}
