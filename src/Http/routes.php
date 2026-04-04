<?php

use AkyrosLabs\Polar\Http\PolarWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/polar/webhook', PolarWebhookController::class)
    ->middleware('api')
    ->name('polar.webhook');
