<?php

use Illuminate\Support\Facades\Artisan;

Artisan::command('app:about', function () {
    $this->info('Minecraft Stats Server');
})->purpose('Display application information');
