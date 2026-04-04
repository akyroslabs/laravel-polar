<?php

use Illuminate\Support\Facades\Route;

Route::post('/polar/webhook', \AkyrosLabs\Polar\Http\PolarWebhookController::class)
    ->middleware('api')
    ->name('polar.webhook');
