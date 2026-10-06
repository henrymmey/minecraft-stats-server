<?php

use Illuminate\Support\Facades\Artisan;

Artisan::command('app:about', function () {
    $this->info('HM Stats Server');
})->purpose('Display application information');
