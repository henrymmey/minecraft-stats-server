<?php

use App\Http\Controllers\Api\V1\BootstrapController;
use App\Http\Controllers\Api\V1\IngestController;
use App\Http\Controllers\Api\V1\Admin\ApiKeyController;
use App\Http\Middleware\AuthenticateApiKey;
use App\Http\Middleware\RequireApiScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('health/live', fn () => response()->json(['status' => 'ok']));

    Route::get('health/ready', function () {
        try {
            DB::select('select 1');
            return response()->json(['status' => 'ok']);
        } catch (\Throwable) {
            return response()->json(['status' => 'unavailable'], 503);
        }
    });

    Route::middleware(['web', 'auth:web'])->group(function () {
        Route::get('admin/me', function (Request $request) {
            $user = $request->user('web');
            $membership = DB::table('workspace_memberships')
                ->where('user_id', $user->id)
                ->first();

            return response()->json([
                'id' => $user->id,
                'display_name' => $user->display_name,
                'email' => $user->email,
                'workspace_id' => $membership?->workspace_id,
                'role' => $membership?->role,
                'needs_bootstrap' => $membership === null,
            ]);
        });

        Route::post('setup/bootstrap', [BootstrapController::class, 'store']);

        Route::middleware('workspace.admin')->prefix('admin')->group(function () {
            Route::get('api-keys', [ApiKeyController::class, 'index']);
            Route::post('api-keys', [ApiKeyController::class, 'store']);
            Route::put('api-keys/{key}', [ApiKeyController::class, 'update']);
            Route::post('api-keys/{key}/rotate', [ApiKeyController::class, 'rotate']);
            Route::post('api-keys/{key}/revoke', [ApiKeyController::class, 'revoke']);

            Route::get('players', [\App\Http\Controllers\Api\V1\Admin\PlayerController::class, 'index']);
            Route::get('servers', [\App\Http\Controllers\Api\V1\Admin\ServerController::class, 'index']);
            Route::post('servers', [\App\Http\Controllers\Api\V1\Admin\ServerController::class, 'store']);
            Route::put('servers/{server}', [\App\Http\Controllers\Api\V1\Admin\ServerController::class, 'update']);
            Route::get('seasons', [\App\Http\Controllers\Api\V1\Admin\SeasonController::class, 'index']);
            Route::post('seasons', [\App\Http\Controllers\Api\V1\Admin\SeasonController::class, 'store']);
            Route::post('seasons/{season}/activate', [\App\Http\Controllers\Api\V1\Admin\SeasonController::class, 'activate']);
            Route::get('users', [\App\Http\Controllers\Api\V1\Admin\UserController::class, 'index']);
            Route::put('users/{user}', [\App\Http\Controllers\Api\V1\Admin\UserController::class, 'update']);
            Route::get('audit-log', [\App\Http\Controllers\Api\V1\Admin\AuditLogController::class, 'index']);
            Route::post('api-keys', [ApiKeyController::class, 'store']);
            Route::put('api-keys/{key}', [ApiKeyController::class, 'update']);
            Route::post('api-keys/{key}/rotate', [ApiKeyController::class, 'rotate']);
            Route::post('api-keys/{key}/revoke', [ApiKeyController::class, 'revoke']);
        });
    });

    Route::middleware(AuthenticateApiKey::class)->group(function () {
        Route::post('ingest/batch', [IngestController::class, 'store'])
            ->middleware('api.scope:ingest:write');

        Route::get('players', [\App\Http\Controllers\Api\V1\PlayerController::class, 'index'])
            ->middleware('api.scope:players:read');
        Route::get('players/{player}', [\App\Http\Controllers\Api\V1\PlayerController::class, 'show'])
            ->middleware('api.scope:players:read');
        Route::get('players/{player}/stats', [\App\Http\Controllers\Api\V1\StatisticsController::class, 'player'])
            ->middleware('api.scope:stats:read');
        Route::get('leaderboards', [\App\Http\Controllers\Api\V1\LeaderboardController::class, 'index'])
            ->middleware('api.scope:leaderboards:read');
        Route::get('online', [\App\Http\Controllers\Api\V1\PresenceController::class, 'index'])
            ->middleware('api.scope:presence:read');
        Route::get('seasons', [\App\Http\Controllers\Api\V1\SeasonController::class, 'index'])
            ->middleware('api.scope:stats:read');
    });
});
