<?php

use App\Http\Controllers\Auth\OidcController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => response()->json([
    'name' => 'Minecraft Stats Server',
    'version' => '0.1.0',
    'status' => 'ok',
]));

Route::get('/auth/login', [OidcController::class, 'login'])->name('auth.login');
Route::get('/auth/callback', [OidcController::class, 'callback'])->name('auth.callback');
Route::post('/auth/logout', [OidcController::class, 'logout'])
    ->middleware('auth:web')
    ->name('auth.logout');
