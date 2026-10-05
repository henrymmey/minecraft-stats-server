<?php

use Illuminate\Support\Facades\Route;

Route::get('/', fn () => response()->json([
    'name' => 'Minecraft Stats Server',
    'version' => '0.1.0',
    'status' => 'ok',
]));
