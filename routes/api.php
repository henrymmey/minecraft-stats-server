<?php

use App\Http\Controllers\Api\V1\IngestController;
use App\Http\Middleware\AuthenticateApiKey;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('health/live', fn () => response()->json(['status' => 'ok']));
    Route::get('health/ready', fn () => response()->json(['status' => 'ok']));

    Route::middleware(AuthenticateApiKey::class)
        ->post('ingest/batch', [IngestController::class, 'store']);
});
