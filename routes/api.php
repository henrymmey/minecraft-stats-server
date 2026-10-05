<?php

use App\Http\Controllers\Api\V1\IngestController;
use App\Http\Middleware\AuthenticateApiKey;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('health/live', fn () => response()->json(['status' => 'ok']));
    Route::get('health/ready', fn () => response()->json(['status' => 'ok']));

    Route::middleware(AuthenticateApiKey::class)
        ->post('ingest/batch', [IngestController::class, 'store']);

    Route::middleware(['auth:web', 'workspace.admin'])->prefix('admin')->group(function () {
        Route::get('me', fn (\Illuminate\Http\Request $request) => response()->json([
            'id' => $request->user('web')->id,
            'display_name' => $request->user('web')->display_name,
            'email' => $request->user('web')->email,
            'workspace_id' => $request->attributes->get('workspace_id'),
            'role' => $request->attributes->get('workspace_role'),
        ]));

        Route::get('api-keys', [\App\Http\Controllers\Api\V1\Admin\ApiKeyController::class, 'index']);
        Route::post('api-keys', [\App\Http\Controllers\Api\V1\Admin\ApiKeyController::class, 'store']);
        Route::post('api-keys/{key}/rotate', [\App\Http\Controllers\Api\V1\Admin\ApiKeyController::class, 'rotate']);
        Route::post('api-keys/{key}/revoke', [\App\Http\Controllers\Api\V1\Admin\ApiKeyController::class, 'revoke']);
    });
});
